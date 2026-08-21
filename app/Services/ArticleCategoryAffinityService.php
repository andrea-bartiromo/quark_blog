<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Collection;

class ArticleCategoryAffinityService
{
    /**
     * @return array<int, string>
     */
    public function slugsFor(Article $article): array
    {
        $secondary = $article->relationLoaded('secondaryCategories')
            ? $article->secondaryCategories->pluck('slug')
            : $article->secondaryCategories()->pluck('categories.slug');

        return collect([$article->category])
            ->merge($secondary)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    public function sharedSlug(array $left, array $right): ?string
    {
        return collect($left)->intersect($right)->first();
    }

    /**
     * Precarica in due query le categorie secondarie di un insieme di
     * articoli, evitando N+1 nei percorsi di ranking/suggerimento.
     *
     * @param  Collection<int, Article>  $articles
     */
    public function loadSecondaryCategories(Collection $articles): Collection
    {
        if ($articles->isNotEmpty()) {
            $articles->loadMissing('secondaryCategories:id,slug');
        }

        return $articles;
    }
}
