<?php

namespace App\Http\Controllers\Redazione;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Services\ArticleRevisionService;
use Illuminate\Http\Request;

/**
 * EDITORIAL SAFETY — "Versioni" per gli articoli Redazione. Stessa
 * autorizzazione già applicata da ArticleController::edit()/update(): un
 * collaboratore vede/ripristina solo le versioni dei PROPRI articoli, e
 * non può ripristinare un articolo pubblicato (stesso motivo per cui non
 * può modificarlo — un ripristino non deve aprire una scorciatoia verso
 * un privilegio che l'editing normale non concede).
 */
class ArticleRevisionController extends Controller
{
    public function __construct(
        private readonly ArticleRevisionService $revisionService,
    ) {}

    public function index(Article $article)
    {
        $this->assertOwnedByCurrentUser($article);

        return view('redazione.article-revisions.index', [
            'article' => $article,
            'revisions' => $article->revisions()->with('user')->paginate(20),
        ]);
    }

    public function show(Article $article, ArticleRevision $revision)
    {
        $this->assertOwnedByCurrentUser($article);
        $this->assertBelongsToArticle($article, $revision);

        return view('redazione.article-revisions.show', [
            'article' => $article,
            'revision' => $revision->load('user'),
        ]);
    }

    public function restore(Request $request, Article $article, ArticleRevision $revision)
    {
        $this->assertOwnedByCurrentUser($article);
        $this->assertBelongsToArticle($article, $revision);

        if ($article->status === Article::STATUS_PUBLISHED) {
            return redirect()
                ->route('redazione.articles')
                ->with('error', 'Gli articoli pubblicati non possono essere modificati. Contatta l\'editor.');
        }

        // Un collaboratore non può normalmente pubblicare o programmare
        // un articolo: ArticleController::update() rimanda sempre il suo
        // salvataggio nello stato review. Consentire il restore diretto di
        // una vecchia revisione published/scheduled aggirerebbe quel gate
        // editoriale anche se l'articolo corrente è tornato draft/review.
        if (in_array($revision->status, [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED], true)) {
            return redirect()
                ->route('redazione.articles.edit', $article)
                ->with('error', 'Questa versione aveva uno stato riservato all\'editor e non può essere ripristinata dalla Redazione.');
        }

        $this->revisionService->restore($article, $revision, $request->user());

        return redirect()
            ->route('redazione.articles.edit', $article)
            ->with('success', 'Versione ripristinata. Lo stato precedente è stato salvato come nuova versione.');
    }

    private function assertOwnedByCurrentUser(Article $article): void
    {
        if ($article->user_id !== auth()->id()) {
            abort(403);
        }
    }

    private function assertBelongsToArticle(Article $article, ArticleRevision $revision): void
    {
        abort_unless($revision->article_id === $article->id, 404);
    }
}
