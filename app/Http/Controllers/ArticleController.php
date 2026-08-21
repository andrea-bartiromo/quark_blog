<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\Category;
use App\Services\ArticlePathNavigation;
use App\Services\ArticleRelatedService;
use App\Services\ArticleViewTrackingService;

class ArticleController extends Controller
{
    public function index()
    {
        return view('notizie', [
            'articles' => Article::published()
                ->with('author')
                ->paginate(12),
        ]);
    }

    public function category(string $slug)
    {
        $categoryModel = Category::where('slug', $slug)->first();
        $categories = Category::options(false);

        abort_unless($categoryModel || array_key_exists($slug, $categories), 404);

        return view('categoria', [
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
            'articles' => Article::published()
                ->where(function ($query) use ($slug) {
                    $query->where('category', $slug)
                        ->orWhereHas('secondaryCategories', fn ($secondaryQuery) => $secondaryQuery->where('categories.slug', $slug));
                })
                ->with('author')
                ->paginate(12),
        ]);
    }

    public function show(string $slug)
    {
        // Nessun eager load di 'comments': i commenti non vengono mai
        // renderizzati sulla pagina pubblica articolo (solo in moderazione
        // admin, resources/views/admin/comments.blade.php) — caricarli qui
        // era una query sprecata a ogni richiesta.
        $article = Article::published()
            ->where('slug', $slug)
            ->with('author')
            ->first();

        if (! $article) {
            $redirect = ArticleSlugRedirect::where('old_slug', $slug)->first();

            // Stessa definizione di "pubblicamente visibile" usata sopra
            // (published() applica anche il controllo su published_at):
            // 301 solo se l'articolo di destinazione è davvero raggiungibile
            // ora, altrimenti si ricade nel normale 404.
            $target = $redirect ? Article::published()->find($redirect->article_id) : null;

            abort_unless($target, 404);

            return redirect()->route('articolo', $target->slug, 301);
        }

        $sessionKey = 'article_viewed_'.$article->id;

        // Il flag di sessione viene impostato solo quando la view è stata
        // davvero registrata: se restasse impostato anche per traffico
        // interno mai contato, potrebbe in teoria mascherare una
        // successiva view pubblica genuina nella stessa sessione.
        if (! session()->has($sessionKey) && app(ArticleViewTrackingService::class)->recordView($article)) {
            session()->put($sessionKey, true);
        }

        return view('articolo', [
            'article' => $article,

            // Correlati multi-categoria: condivide almeno una categoria
            // principale o secondaria con l'articolo corrente.
            'related' => app(ArticleRelatedService::class)->forArticle($article),

            // Calcolato una sola volta qui e riusato da articolo.blade.php,
            // articles/partials/structured-data.blade.php e
            // articles/partials/breadcrumb.blade.php (unici consumer di
            // questo partial, vedi grep): prima ciascuno rieseguiva la
            // stessa query "select name, slug from categories" per conto
            // proprio, 4 query identiche per singola pagina articolo.
            'categoryOptions' => Category::options(false),

            'pathNavigation' => app(ArticlePathNavigation::class)->forArticle($article),
        ]);
    }
}
