<?php

namespace App\Observers;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Services\ContentClusterSuggestionService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ContentClusterSuggestionObserver
{
    public function created(Article $article): void
    {
        $this->scheduleArticleRefresh($article->id);
    }

    public function updated(Article $article): void
    {
        if (! $article->wasChanged(['slug', 'category'])) {
            return;
        }

        $this->scheduleArticleRefresh($article->id);

        if ($article->wasChanged('category')) {
            $clusterIds = DB::table('article_content_cluster')
                ->where('article_id', $article->id)
                ->pluck('content_cluster_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->scheduleClusterCategoryRefresh($clusterIds, [
                $article->getOriginal('category'),
                $article->category,
            ]);
        }
    }

    public function deleting(Article $article): void
    {
        $article->setRelation(
            'contentClusterSuggestionRefreshIds',
            DB::table('article_content_cluster')
                ->where('article_id', $article->id)
                ->pluck('content_cluster_id')
                ->map(fn ($id) => (int) $id)
        );
    }

    public function deleted(Article $article): void
    {
        $clusterIds = $article->getRelation('contentClusterSuggestionRefreshIds')?->all() ?? [];

        $this->scheduleClusterCategoryRefresh($clusterIds, [$article->category]);
    }

    private function scheduleArticleRefresh(int $articleId): void
    {
        DB::afterCommit(function () use ($articleId): void {
            try {
                $article = Article::query()->find($articleId);

                if ($article !== null) {
                    app(ContentClusterSuggestionService::class)->refreshForArticle($article);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    private function scheduleClusterCategoryRefresh(array $clusterIds, array $categories): void
    {
        $clusterIds = collect($clusterIds)->unique()->values()->all();
        $categories = collect($categories)
            ->filter(fn ($category) => is_string($category) && $category !== '')
            ->unique()
            ->values()
            ->all();

        if ($clusterIds === [] || $categories === []) {
            return;
        }

        DB::afterCommit(function () use ($clusterIds, $categories): void {
            try {
                $clusters = ContentCluster::query()->whereIn('id', $clusterIds)->get();

                foreach ($clusters as $cluster) {
                    foreach ($categories as $category) {
                        app(ContentClusterSuggestionService::class)->refreshForClusterCategory($cluster, $category);
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
