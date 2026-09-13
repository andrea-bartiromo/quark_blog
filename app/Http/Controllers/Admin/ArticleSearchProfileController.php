<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateArticleSearchProfileRequest;
use App\Models\Article;
use App\Models\ArticleSearchProfile;
use Illuminate\Http\RedirectResponse;

/**
 * Cantiere 2 (programma "Kairus Organic Discovery"). Endpoint dedicato e
 * separato da Admin\ArticleController volutamente: il profilo di ricerca
 * è un 1:1 opzionale salvabile solo su un articolo già esistente (serve
 * article_id), esattamente come il collegamento concetti
 * (ArticleConceptController) o la certificazione primo piano — mai
 * infilato nel costruttore di ArticleController, che ArticleDiscoveryController
 * duplica posizionalmente per ogni route admin.articles.*: una dipendenza
 * in più lì romperebbe quella sottoclasse.
 */
class ArticleSearchProfileController extends Controller
{
    public function update(Article $article, UpdateArticleSearchProfileRequest $request): RedirectResponse
    {
        ArticleSearchProfile::query()->updateOrCreate(
            ['article_id' => $article->id],
            $request->validated(),
        );

        return redirect()
            ->route('admin.articles.edit', $article)
            ->with('status', 'Profilo di ricerca editoriale salvato.');
    }
}
