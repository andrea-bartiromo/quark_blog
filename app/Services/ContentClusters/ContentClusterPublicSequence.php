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
     * Resolve one Percorso from the database. Public eligibility is delegated
     * to Article::published(), never reconstructed from status strings here.
     *
     * @return array{
     *     articles: Collection<int, Article>,
     *     has_hidden_remainder: bool
     * }
     */
    public function resolve(ContentCluster $cluster): array
    {
        $ordered = $cluster->articles()->get();

        if ($ordered->isEmpty()) {
            return $this->emptyResult();
        }

        $publishedIds = Article::query()
            ->published()
            ->whereIn('id', $ordered->pluck('id'))
            ->pluck('id');

        return $this->fromOrdered($ordered, $publishedIds);
    }

    /**
     * Bounded corpus variant for audit/analytics consumers that already loaded
     * all memberships and already own a published-id corpus produced by
     * Article::published(). It applies the exact same stop-at-first-gap rule
     * without issuing one query per Percorso.
     *
     * @param  Collection<int, int|string>  $publishedArticleIds
     * @return array{
     *     articles: Collection<int, Article>,
     *     has_hidden_remainder: bool
     * }
     */
    public function resolveLoaded(ContentCluster $cluster, Collection $publishedArticleIds): array
    {
        $ordered = $cluster->relationLoaded('articles')
            ? $cluster->articles
                ->sortBy(fn (Article $article) => [$article->pivot?->position ?? PHP_INT_MAX, $article->id])
                ->values()
            : $cluster->articles()->get();

        if ($ordered->isEmpty()) {
            return $this->emptyResult();
        }

        return $this->fromOrdered($ordered, $publishedArticleIds);
    }

    /**
     * Missione 18 (secondo batch autonomo KAIRUS, Fase C — Percorsi
     * Advanced Operations): stessa identica regola "si ferma al primo
     * membro non pubblico" di resolve()/resolveLoaded(), ma su un ordine
     * fornito esplicitamente dal chiamante invece che dalla relazione
     * reale del Percorso — l'unico punto di ingresso pensato per un
     * servizio di simulazione (es. PercorsoReorderSimulationService) che
     * deve valutare un ordine PROPOSTO senza mai leggerlo dal database.
     * Nessuna nuova logica di prefisso: solo un secondo punto di
     * ingresso verso la stessa fromOrdered() già usata e testata da
     * resolve()/resolveLoaded().
     *
     * @param  Collection<int, Article>  $orderedArticles
     * @param  Collection<int, int|string>  $publishedArticleIds
     * @return array{articles:Collection<int,Article>,has_hidden_remainder:bool}
     */
    public function resolveFromOrder(Collection $orderedArticles, Collection $publishedArticleIds): array
    {
        if ($orderedArticles->isEmpty()) {
            return $this->emptyResult();
        }

        return $this->fromOrdered($orderedArticles, $publishedArticleIds);
    }

    /**
     * @param  Collection<int, Article>  $ordered
     * @param  Collection<int, int|string>  $publishedArticleIds
     * @return array{articles:Collection<int,Article>,has_hidden_remainder:bool}
     */
    private function fromOrdered(Collection $ordered, Collection $publishedArticleIds): array
    {
        $publishedIds = $publishedArticleIds
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

    /** @return array{articles:Collection<int,Article>,has_hidden_remainder:bool} */
    private function emptyResult(): array
    {
        return [
            'articles' => collect(),
            'has_hidden_remainder' => false,
        ];
    }
}
