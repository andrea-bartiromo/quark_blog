<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Collection;

class ArticleRelatedService
{
    /**
     * Restituisce articoli pubblicati che condividono almeno una categoria
     * con la sorgente, considerando sia la categoria principale sia quelle
     * secondarie. L'uso di whereHas() evita duplicati anche quando due
     * articoli condividono più di una categoria.
     *
     * @return Collection<int, Article>
     */
    public function forArticle(Article $article, int $limit = 3): Collection
    {
        $categorySlugs = collect([$article->category])
            ->merge($article->secondaryCategories()->pluck('categories.slug'))
            ->filter()
            ->unique()
            ->values();

        if ($categorySlugs->isEmpty()) {
            return collect();
        }

        return Article::published()
            ->where('id', '!=', $article->id)
            ->where(function ($query) use ($categorySlugs) {
                $query->whereIn('category', $categorySlugs)
                    ->orWhereHas('secondaryCategories', function ($secondaryQuery) use ($categorySlugs) {
                        $secondaryQuery->whereIn('categories.slug', $categorySlugs);
                    });
            })
            ->limit($limit)
            ->get();
    }
}
