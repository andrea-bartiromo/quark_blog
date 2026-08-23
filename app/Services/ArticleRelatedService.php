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
     * $excludeIds: id aggiuntivi da escludere oltre all'articolo stesso —
     * pensato per l'articolo precedente/successivo già mostrato da
     * articles/partials/path-continuation.blade.php quando l'articolo
     * appartiene a un Percorso attivo (vedi ArticlePathNavigation). Senza
     * questa esclusione, un Percorso tematicamente coerente (il caso
     * comune: stessa categoria per tutte le tappe) produce quasi sempre lo
     * stesso identico articolo mostrato due volte di seguito nella stessa
     * pagina — prima come "prossima tappa" del percorso, poi di nuovo
     * nella sezione "Continua a leggere" subito sotto.
     *
     * @param  array<int, int>  $excludeIds
     * @return Collection<int, Article>
     */
    public function forArticle(Article $article, int $limit = 3, array $excludeIds = []): Collection
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
            ->whereNotIn('id', [$article->id, ...$excludeIds])
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
