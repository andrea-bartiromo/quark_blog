<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Services\ArticleRevisionService;
use Illuminate\Http\Request;

/**
 * EDITORIAL SAFETY — "Versioni" per gli articoli Admin. Nessuna
 * autorizzazione per-articolo oltre al middleware ['auth','editor'] già
 * applicato all'intero gruppo di rotte admin.*: qualunque editor può già
 * modificare qualunque articolo (vedi ArticleController::edit()), quindi
 * può già vederne/ripristinarne le versioni — stessa autorizzazione, non
 * una più ampia.
 */
class ArticleRevisionController extends Controller
{
    public function __construct(
        private readonly ArticleRevisionService $revisionService,
    ) {}

    public function index(Article $article)
    {
        return view('admin.article-revisions.index', [
            'article' => $article,
            'revisions' => $article->revisions()->with('user')->paginate(20),
        ]);
    }

    public function show(Article $article, ArticleRevision $revision)
    {
        $this->assertBelongsToArticle($article, $revision);

        return view('admin.article-revisions.show', [
            'article' => $article,
            'revision' => $revision->load('user'),
        ]);
    }

    public function restore(Request $request, Article $article, ArticleRevision $revision)
    {
        $this->assertBelongsToArticle($article, $revision);

        $this->revisionService->restore($article, $revision, $request->user());

        return redirect()
            ->route('admin.articles.edit', $article)
            ->with('success', 'Versione ripristinata. Lo stato precedente è stato salvato come nuova versione.');
    }

    private function assertBelongsToArticle(Article $article, ArticleRevision $revision): void
    {
        abort_unless($revision->article_id === $article->id, 404);
    }
}
