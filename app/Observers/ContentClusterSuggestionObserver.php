<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\ContentClusterSuggestionService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ContentClusterSuggestionObserver
{
    public function created(Article $article): void
    {
        $this->scheduleRefresh($article->id);
    }

    public function updated(Article $article): void
    {
        if (! $article->wasChanged(['slug', 'category'])) {
            return;
        }

        $this->scheduleRefresh($article->id);
    }

    private function scheduleRefresh(int $articleId): void
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
}
