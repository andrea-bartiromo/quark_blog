<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        foreach (['EMPTY', 'NO_PILLAR', 'NO_PUBLIC_ARTICLES', 'ORDERING_ISSUE', 'PRIMARY_GAPS'] as $status) {
            if ($findings->contains($status)) {
                return $status;
            }
        }

        return $findings->isEmpty() ? 'HEALTHY' : 'INCOMPLETE';
    }

    /** @return array<string, Collection<int, Article>> */
    public function orphans(): array
    {
        $covered = DB::table('article_content_cluster')->distinct()->pluck('article_id');
        $primary = DB::table('article_content_cluster')->where('is_primary', true)->distinct()->pluck('article_id');
        $activeCovered = DB::table('article_content_cluster')
            ->join('content_clusters', 'content_clusters.id', '=', 'article_content_cluster.content_cluster_id')
            ->where('content_clusters.is_active', true)
            ->distinct()
            ->pluck('article_content_cluster.article_id');

        return [
            'without_cluster' => Article::query()->whereNotIn('id', $covered)->orderBy('title')->get(),
            'without_primary' => Article::query()->whereIn('id', $covered)->whereNotIn('id', $primary)->orderBy('title')->get(),
            'inactive_only' => Article::query()->whereIn('id', $covered)->whereNotIn('id', $activeCovered)->orderBy('title')->get(),
            'published_uncovered' => Article::query()->published()->whereNotIn('id', $activeCovered)->get(),
            'scheduled_uncovered' => Article::query()->scheduled()->whereNotIn('id', $activeCovered)->get(),
        ];
    }
}
