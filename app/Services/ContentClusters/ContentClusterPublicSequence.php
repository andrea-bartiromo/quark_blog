<?php

namespace App\Services\ContentClusters;

use App\Models\Article;
use App\Models\ContentCluster;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the publicly traversable part of a Percorso.
 *
 * Editorial order is authoritative: members are examined from the first
 * position and the public sequence stops at the first member that does not
 * satisfy Article::published(). Later published members remain deliberately
 * hidden until every preceding step is public.
 */
class ContentClusterPublicSequence
{
    /**
     * @return array{
     *     articles: Collection<int, Article>,
     *     has_hidden_remainder: bool
     * }
     */
    public function resolve(ContentCluster $cluster): array
    {
        $ordered = $cluster->articles()->get();

        if ($ordered->isEmpty()) {
            return [
                'articles' => collect(),
                'has_hidden_remainder' => false,
            ];
        }

        $publishedIds = Article::query()
            ->published()
            ->whereIn('id', $ordered->pluck('id'))
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true]);

        $public = collect();
        $hasHiddenRemainder = false;

        foreach ($ordered as $article) {
            if (! $publishedIds->has((int) $article->id)) {
                $hasHiddenRemainder = true;
                break;
            }

            $public->push($article);
        }

        return [
            'articles' => $public->values(),
            'has_hidden_remainder' => $hasHiddenRemainder,
        ];
    }
}
