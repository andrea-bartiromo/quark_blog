<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContentClusterMembershipService
{
    /**
     * @param  array<int, array{article_id:int, position?:int|null, is_primary?:bool}>  $memberships
     */
    public function sync(ContentCluster $cluster, array $memberships, ?int $pillarArticleId): void
    {
        DB::transaction(function () use ($cluster, $memberships, $pillarArticleId) {
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
        });
    }

    /**
     * Apply a versioned mapping without deleting editorial data outside it.
     * Primary conflicts are fail-safe: conflicting rows are skipped by caller.
     *
     * @param  array<int, array{article_id:int, position:int, is_primary:bool}>  $memberships
     */
    public function applyMapped(ContentCluster $cluster, array $memberships, ?int $pillarArticleId): void
    {
        DB::transaction(function () use ($cluster, $memberships, $pillarArticleId) {
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
        });
    }
}
