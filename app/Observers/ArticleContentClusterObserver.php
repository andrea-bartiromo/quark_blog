<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\ArticleContentCluster;
use App\Models\ContentCluster;
use App\Services\ContentClusterSuggestionService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ArticleContentClusterObserver
{
    public function created(ArticleContentCluster $pivot): void
    {
        $this->scheduleRefresh((int) $pivot->article_id, (int) $pivot->content_cluster_id);
    }

    public function deleted(ArticleContentCluster $pivot): void
    {
        $this->scheduleRefresh((int) $pivot->article_id, (int) $pivot->content_cluster_id);
    }

    private function scheduleRefresh(int $articleId, int $clusterId): void
    {
        DB::afterCommit(function () use ($articleId, $clusterId): void {
            try {
                $article = Article::query()->find($articleId);
                $cluster = ContentCluster::query()->find($clusterId);

                if ($article?->category && $cluster !== null) {
                    app(ContentClusterSuggestionService::class)->refreshForClusterCategory($cluster, $article->category);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
