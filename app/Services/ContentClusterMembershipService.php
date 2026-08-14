<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContentClusterMembershipService
{
    /**
     * @param  array<int, array{article_id:int, position?:int|null, is_primary?:bool}>  $memberships
     */
    public function sync(ContentCluster $cluster, array $memberships, ?int $pillarArticleId): void
    {
        $changedCategories = DB::transaction(function () use ($cluster, $memberships, $pillarArticleId): array {
            $beforeIds = DB::table('article_content_cluster')
                ->where('content_cluster_id', $cluster->id)
                ->pluck('article_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $articleIds = collect($memberships)->pluck('article_id')->map(fn ($id) => (int) $id)->unique()->values();

            if ($pillarArticleId !== null && ! $articleIds->contains($pillarArticleId)) {
                throw ValidationException::withMessages([
                    'pillar_article_id' => 'Il pillar deve appartenere al Percorso.',
                ]);
            }

            if ($articleIds->isNotEmpty()) {
                Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get(['id']);
            }

            $normalized = collect($memberships)
                ->sortBy(fn (array $membership) => [
                    $membership['position'] ?? PHP_INT_MAX,
                    $membership['article_id'],
                ])
                ->values();

            $sync = [];
            foreach ($normalized as $index => $membership) {
                $articleId = (int) $membership['article_id'];
                $isPrimary = (bool) ($membership['is_primary'] ?? false);

                if ($isPrimary) {
                    DB::table('article_content_cluster')
                        ->where('article_id', $articleId)
                        ->where('content_cluster_id', '!=', $cluster->id)
                        ->update(['is_primary' => false, 'updated_at' => now()]);
                }

                $sync[$articleId] = [
                    'position' => ($index + 1) * 10,
                    'is_primary' => $isPrimary,
                ];
            }

            $cluster->articles()->sync($sync);
            $cluster->update(['pillar_article_id' => $pillarArticleId]);

            $changedIds = $beforeIds
                ->diff($articleIds)
                ->merge($articleIds->diff($beforeIds))
                ->unique()
                ->values();

            if ($changedIds->isEmpty()) {
                return [];
            }

            return Article::query()
                ->whereIn('id', $changedIds)
                ->whereNotNull('category')
                ->pluck('category')
                ->filter(fn ($category) => $category !== '')
                ->unique()
                ->values()
                ->all();
        });

        $this->scheduleCategoryRefresh($cluster->id, $changedCategories);
    }

    public function addMembership(ContentCluster $cluster, Article $article, bool $primary = false): void
    {
        $membershipAdded = DB::transaction(function () use ($cluster, $article, $primary): bool {
            Article::query()->whereKey($article->id)->lockForUpdate()->firstOrFail();

            $existing = DB::table('article_content_cluster')
                ->where('content_cluster_id', $cluster->id)
                ->where('article_id', $article->id)
                ->first();

            if ($primary) {
                $otherPrimaryExists = DB::table('article_content_cluster')
                    ->where('article_id', $article->id)
                    ->where('content_cluster_id', '!=', $cluster->id)
                    ->where('is_primary', true)
                    ->exists();

                if ($otherPrimaryExists) {
                    throw ValidationException::withMessages([
                        'suggestion' => 'L’articolo ha già un altro Percorso primary. Nessuna modifica applicata.',
                    ]);
                }
            }

            if ($existing !== null) {
                if ($primary && ! (bool) $existing->is_primary) {
                    DB::table('article_content_cluster')
                        ->where('content_cluster_id', $cluster->id)
                        ->where('article_id', $article->id)
                        ->update(['is_primary' => true, 'updated_at' => now()]);
                }

                return false;
            }

            $nextPosition = ((int) DB::table('article_content_cluster')
                ->where('content_cluster_id', $cluster->id)
                ->max('position')) + 10;

            $cluster->articles()->attach($article->id, [
                'position' => max(10, $nextPosition),
                'is_primary' => $primary,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });

        if ($membershipAdded) {
            $this->scheduleCategoryRefresh($cluster->id, [$article->category]);
        }
    }

    /**
     * Apply a versioned mapping without deleting editorial data outside it.
     * Primary conflicts are fail-safe: conflicting rows are skipped by caller.
     *
     * @param  array<int, array{article_id:int, position:int, is_primary:bool}>  $memberships
     */
    public function applyMapped(ContentCluster $cluster, array $memberships, ?int $pillarArticleId): void
    {
        $changedCategories = DB::transaction(function () use ($cluster, $memberships, $pillarArticleId): array {
            $beforeIds = DB::table('article_content_cluster')
                ->where('content_cluster_id', $cluster->id)
                ->pluck('article_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            $articleIds = collect($memberships)->pluck('article_id')->map(fn ($id) => (int) $id)->unique()->values();

            if ($articleIds->isNotEmpty()) {
                Article::query()->whereIn('id', $articleIds)->orderBy('id')->lockForUpdate()->get(['id']);
            }

            foreach ($memberships as $membership) {
                $articleId = (int) $membership['article_id'];
                $isPrimary = (bool) $membership['is_primary'];

                if ($isPrimary) {
                    $hasOtherPrimary = DB::table('article_content_cluster')
                        ->where('article_id', $articleId)
                        ->where('content_cluster_id', '!=', $cluster->id)
                        ->where('is_primary', true)
                        ->exists();

                    if ($hasOtherPrimary) {
                        continue;
                    }
                }

                $cluster->articles()->syncWithoutDetaching([
                    $articleId => [
                        'position' => (int) $membership['position'],
                        'is_primary' => $isPrimary,
                    ],
                ]);
            }

            if ($pillarArticleId !== null) {
                $isMember = DB::table('article_content_cluster')
                    ->where('content_cluster_id', $cluster->id)
                    ->where('article_id', $pillarArticleId)
                    ->exists();

                if (! $isMember) {
                    throw ValidationException::withMessages([
                        'pillar_article_id' => 'Il pillar deve appartenere al Percorso.',
                    ]);
                }

                $cluster->update(['pillar_article_id' => $pillarArticleId]);
            }

            $addedIds = DB::table('article_content_cluster')
                ->where('content_cluster_id', $cluster->id)
                ->pluck('article_id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn ($id) => $beforeIds->has($id))
                ->values();

            if ($addedIds->isEmpty()) {
                return [];
            }

            return Article::query()
                ->whereIn('id', $addedIds)
                ->whereNotNull('category')
                ->pluck('category')
                ->filter(fn ($category) => $category !== '')
                ->unique()
                ->values()
                ->all();
        });

        $this->scheduleCategoryRefresh($cluster->id, $changedCategories);
    }

    /**
     * @param  array<int, string|null>  $categories
     */
    private function scheduleCategoryRefresh(int $clusterId, array $categories): void
    {
        $categories = collect($categories)
            ->filter(fn ($category) => is_string($category) && $category !== '')
            ->unique()
            ->values()
            ->all();

        if ($categories === []) {
            return;
        }

        DB::afterCommit(function () use ($clusterId, $categories): void {
            try {
                $cluster = ContentCluster::query()->find($clusterId);

                if ($cluster === null) {
                    return;
                }

                foreach ($categories as $category) {
                    app(ContentClusterSuggestionService::class)->refreshForClusterCategory($cluster, $category);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
