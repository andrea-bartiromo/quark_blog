<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContentClusterHealth
{
    /** @return array<string, mixed> */
    public function evaluate(ContentCluster $cluster, ?Collection $globallyPrimaryArticleIds = null): array
    {
        $articles = $cluster->relationLoaded('articles') ? $cluster->articles : $cluster->articles()->get();
        $articleIds = $articles->pluck('id')->map(fn ($id) => (int) $id)->values();
        $globallyPrimaryArticleIds ??= $this->primaryArticleIds($articleIds);
        $now = now();
        $published = $articles->filter(fn (Article $article) => $article->status === Article::STATUS_PUBLISHED && $article->published_at?->lte($now));
        $scheduled = $articles->filter(fn (Article $article) => $article->status === Article::STATUS_SCHEDULED);
        $primaryCount = $articleIds->intersect($globallyPrimaryArticleIds)->unique()->count();
        $positions = $articles->pluck('pivot.position')->filter(fn ($position) => $position !== null)->map(fn ($position) => (int) $position);
        $orderingValid = $positions->count() === $articles->count() && $positions->unique()->count() === $positions->count() && $positions->every(fn (int $position) => $position > 0);
        $pillar = $cluster->relationLoaded('pillarArticle') ? $cluster->pillarArticle : $cluster->pillarArticle()->first();
        $pillarPublic = $pillar !== null && $pillar->status === Article::STATUS_PUBLISHED && $pillar->published_at?->lte($now);

        $findings = collect();
        if ($articles->isEmpty()) {
            $findings->push('EMPTY');
        }
        if ($pillar === null) {
            $findings->push('NO_PILLAR');
        } elseif (! $pillarPublic) {
            $findings->push('PILLAR_NOT_PUBLIC');
        }
        if ($articles->isNotEmpty() && $published->isEmpty()) {
            $findings->push('NO_PUBLIC_ARTICLES');
        }
        if ($articles->isNotEmpty() && $primaryCount < $articles->count()) {
            $findings->push('PRIMARY_GAPS');
        }
        if (! $orderingValid) {
            $findings->push('ORDERING_ISSUE');
        }

        return [
            'active' => (bool) $cluster->is_active,
            'pillar_present' => $pillar !== null,
            'pillar_public' => $pillarPublic,
            'article_count_total' => $articles->count(),
            'article_count_published' => $published->count(),
            'primary_coverage' => $articles->isEmpty() ? 0 : (int) round(($primaryCount / $articles->count()) * 100),
            'ordering_valid' => $orderingValid,
            'has_public_sequence' => $published->isNotEmpty(),
            'scheduled_count' => $scheduled->count(),
            'findings' => $findings->values()->all(),
            'status' => $this->status($findings),
        ];
    }

    /** @param Collection<int, int> $articleIds */
    public function primaryArticleIds(Collection $articleIds): Collection
    {
        if ($articleIds->isEmpty()) {
            return collect();
        }

        return DB::table('article_content_cluster')
            ->whereIn('article_id', $articleIds->unique()->values())
            ->where('is_primary', true)
            ->distinct()
            ->pluck('article_id')
            ->map(fn ($id) => (int) $id);
    }

    public function status(Collection $findings): string
    {
        foreach (['EMPTY', 'NO_PILLAR', 'PILLAR_NOT_PUBLIC', 'NO_PUBLIC_ARTICLES', 'ORDERING_ISSUE', 'PRIMARY_GAPS'] as $status) {
            if ($findings->contains($status)) {
                return $status;
            }
        }

        return $findings->isEmpty() ? 'HEALTHY' : 'INCOMPLETE';
    }

    /** @return array<string, int> */
    public function orphanCounts(): array
    {
        $activeMembership = fn ($query) => $query->where('content_clusters.is_active', true);
        $primaryMembership = fn ($query) => $query->where('article_content_cluster.is_primary', true);

        return [
            'without_cluster' => Article::query()->whereDoesntHave('contentClusters')->count(),
            'without_primary' => Article::query()->whereHas('contentClusters')->whereDoesntHave('contentClusters', $primaryMembership)->count(),
            'inactive_only' => Article::query()->whereHas('contentClusters')->whereDoesntHave('contentClusters', $activeMembership)->count(),
            'published_uncovered' => Article::query()->published()->whereDoesntHave('contentClusters', $activeMembership)->count(),
            'scheduled_uncovered' => Article::query()->scheduled()->whereDoesntHave('contentClusters', $activeMembership)->count(),
        ];
    }
}
