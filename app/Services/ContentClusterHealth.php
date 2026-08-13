<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Collection;

class ContentClusterHealth
{
    /** @return array<string, mixed> */
    public function evaluate(ContentCluster $cluster): array
    {
        $articles = $cluster->relationLoaded('articles') ? $cluster->articles : $cluster->articles()->get();
        $now = now();
        $published = $articles->filter(fn (Article $article) => $article->status === Article::STATUS_PUBLISHED && $article->published_at?->lte($now));
        $scheduled = $articles->filter(fn (Article $article) => $article->status === Article::STATUS_SCHEDULED);
        $primaryCount = $articles->filter(fn (Article $article) => (bool) $article->pivot?->is_primary)->count();
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

    public function status(Collection $findings): string
    {
        if ($findings->contains('EMPTY')) {
            return 'EMPTY';
        }
        if ($findings->contains('NO_PILLAR')) {
            return 'NO_PILLAR';
        }
        if ($findings->contains('NO_PUBLIC_ARTICLES')) {
            return 'NO_PUBLIC_ARTICLES';
        }
        if ($findings->contains('ORDERING_ISSUE')) {
            return 'ORDERING_ISSUE';
        }
        if ($findings->contains('PRIMARY_GAPS')) {
            return 'PRIMARY_GAPS';
        }

        return $findings->isEmpty() ? 'HEALTHY' : 'INCOMPLETE';
    }

    /** @return array<string, \Illuminate\Support\Collection<int, Article>> */
    public function orphans(): array
    {
        $base = Article::query()->with(['contentClusters:id,is_active']);

        return [
            'without_cluster' => (clone $base)->whereDoesntHave('contentClusters')->get(),
            'without_primary' => (clone $base)->whereHas('contentClusters')->whereDoesntHave('contentClusters', fn ($q) => $q->where('article_content_cluster.is_primary', true))->get(),
            'inactive_only' => (clone $base)->whereHas('contentClusters')->whereDoesntHave('contentClusters', fn ($q) => $q->where('content_clusters.is_active', true))->get(),
            'published_uncovered' => (clone $base)->published()->whereDoesntHave('contentClusters', fn ($q) => $q->where('content_clusters.is_active', true))->get(),
            'scheduled_uncovered' => (clone $base)->scheduled()->whereDoesntHave('contentClusters', fn ($q) => $q->where('content_clusters.is_active', true))->get(),
        ];
    }
}
