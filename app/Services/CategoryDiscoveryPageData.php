<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

/**
 * Cantiere 11 (programma 100-cantieri Kairus): estratto da
 * ArticleController::category() perché la stessa identica composizione
 * (griglia paginata, "Più letti", chip Argomenti) serve anche
 * all'anteprima admin di una categoria bozza/programmata
 * (Admin\CategoryController::preview()) — mai il controllo di visibilità
 * pubblica, che resta responsabilità esclusiva del chiamante (category()
 * lo applica prima di chiamare build(), preview() lo salta di proposito).
 * Estrarre qui evita che le due renderizzazioni (pubblica e anteprima)
 * possano divergere nel tempo.
 */
class CategoryDiscoveryPageData
{
    /**
     * @param  ?Category  $categoryModel  Già risolta dal chiamante (per la
     *                                    verifica di visibilità in
     *                                    category(), dal route-model-binding
     *                                    in preview()) — mai una seconda
     *                                    query "select ... where slug = ?"
     *                                    qui dentro per la stessa riga.
     * @param  callable(int): string  $pageUrl  Costruisce l'URL di una pagina
     *                                          della griglia: category()
     *                                          punta a route('categoria', ...),
     *                                          preview() alla propria rotta
     *                                          admin, così la paginazione
     *                                          resta dentro l'anteprima.
     * @return array<string, mixed>|null null quando lo slug non risolve a
     *                                   nessuna categoria (né riga DB né
     *                                   voce di config) o quando la pagina
     *                                   richiesta è oltre l'ultima
     *                                   disponibile — in entrambi i casi il
     *                                   chiamante deve rispondere 404.
     */
    public function build(Request $request, string $slug, ?Category $categoryModel, callable $pageUrl): ?array
    {
        $categories = Category::options(false);

        if (! $categoryModel && ! array_key_exists($slug, $categories)) {
            return null;
        }

        $articles = Article::published()
            ->where(function ($query) use ($slug) {
                $query->where('category', $slug)
                    ->orWhereHas('secondaryCategories', fn ($secondaryQuery) => $secondaryQuery->where('categories.slug', $slug));
            })
            ->orderByDesc('id')
            ->with('author')
            // Sei card sono la misura editoriale della griglia pubblica:
            // abbastanza per scoprire, senza trasformare l'archivio in una
            // lista infinita. withQueryString() conserva eventuali filtri
            // già presenti nei link HTML della navigazione.
            ->paginate(6)
            ->withQueryString();

        // Anche una categoria senza articoli ha una sola pagina valida:
        // ?page=2 (o maggiore) deve essere un vero 404, mai una griglia
        // vuota con HTTP 200.
        if ($articles->currentPage() > $articles->lastPage()) {
            return null;
        }

        // Blocco "Continua a esplorare" (Cantiere 1, programma 100-cantieri
        // Kairus): tre articoli tra i più letti, mai un duplicato di quelli
        // già mostrati in questa stessa pagina di griglia — altrimenti un
        // lettore che ha appena scorso 6 card vedrebbe una di quelle stesse
        // sei ripetuta subito sotto come "consigliata".
        $mostRead = Article::published()
            ->whereNotIn('id', $articles->pluck('id'))
            ->orderByDesc('views')
            ->limit(3)
            ->get(['title', 'slug', 'category', 'read_minutes']);

        // Chip Argomenti + categorie correlate: SOLO categorie genuinamente
        // pubbliche (Category::publicOptions(), la stessa già usata dal
        // pill-row di notizie.blade.php) — mai la lista di $categories sopra,
        // che include anche bozza/programmata/disattivata per il solo
        // controllo 404. $categoryLabelOptions invece riusa $categories
        // già recuperata (nessuna query aggiuntiva): i badge di "Più letti"
        // devono restare leggibili anche per una categoria nel frattempo
        // disattivata, stessa semantica già in components/sidebar.blade.php.
        $publicCategoryOptions = Category::publicOptions();

        return [
            'slug' => $slug,
            'categoryModel' => $categoryModel,
            'categoryLabel' => $categoryModel?->name ?? $categories[$slug],
            'categoryDescription' => $categoryModel?->description,
            'categoryImage' => $categoryModel?->image,
            'category' => $slug,

            // Discovery multi-categoria: la pagina mostra gli articoli che
            // hanno questa categoria come principale oppure come secondaria.
            // whereHas() usa EXISTS e quindi non duplica le righe anche se
            // un articolo soddisfacesse entrambe le condizioni.
            'articles' => $articles,
            'firstPageUrl' => $pageUrl(1),
            'previousPageUrl' => $articles->onFirstPage() ? null : $pageUrl($articles->currentPage() - 1),
            'nextPageUrl' => $articles->hasMorePages() ? $pageUrl($articles->currentPage() + 1) : null,
            'mostRead' => $mostRead,
            'categoryOptions' => $publicCategoryOptions,
            'categoryLabelOptions' => $categories,
        ];
    }
}
