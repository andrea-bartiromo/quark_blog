<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ContentCluster;

class ArticlePathNavigation
{
    /**
     * @return array{cluster:ContentCluster,current_index:int,total:int,previous:?Article,next:?Article,path_url:string}|null
     */
    public function forArticle(Article $article): ?array
    {
        $cluster = ContentCluster::query()
            ->select('content_clusters.*')
            ->join('article_content_cluster as membership', 'membership.content_cluster_id', '=', 'content_clusters.id')
            ->where('membership.article_id', $article->id)
            ->where('content_clusters.is_active', true)
            ->orderByDesc('membership.is_primary')
            ->orderBy('content_clusters.sort_order')
            ->orderBy('content_clusters.name')
            ->orderBy('content_clusters.id')
            ->first();

        if (! $cluster) {
            return null;
        }

        $articles = $cluster->articles()
            ->published()
            ->get(['articles.id', 'articles.title', 'articles.slug', 'articles.published_at']);

        $currentIndex = $articles->search(fn (Article $candidate) => $candidate->id === $article->id);

        if ($currentIndex === false) {
            return null;
        }

        return [
            'cluster' => $cluster,
            'current_index' => $currentIndex + 1,
            'total' => $articles->count(),
            'previous' => $currentIndex > 0 ? $articles->get($currentIndex - 1) : null,
            'next' => $currentIndex < $articles->count() - 1 ? $articles->get($currentIndex + 1) : null,
            'path_url' => route('percorsi.show', $cluster->slug),
        ];
    }
}
