<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint condivisi tra Admin e Redazione per i suggerimenti di
 * collegamento interno (vedi App\Services\ArticleLinkSuggestionService e
 * App\Services\ArticleLinkInsertionService). Nessuna delle tre azioni
 * salva l'articolo: "analizza" persiste solo le righe di suggerimento,
 * "inserisci" restituisce il body aggiornato perché il form lo rimetta in
 * TinyMCE — il salvataggio resta sempre un'azione umana esplicita
 * ("Salva modifiche" / "Crea articolo").
 */
class ArticleLinkSuggestionController extends Controller
{
    public function __construct(
        private readonly ArticleLinkSuggestionService $suggestionService,
        private readonly ArticleLinkInsertionService $insertionService,
    ) {}

    /**
     * Calcola/aggiorna i suggerimenti per $article e restituisce l'elenco
     * completo di quelli attualmente proposti (azione esplicita "Analizza
     * collegamenti interni" — mai automatica al caricamento del form,
     * FASE 8).
     */
    public function analyze(Article $article): JsonResponse
    {
        $this->authorizeArticleAccess($article);

        $this->suggestionService->analyzeForSource($article);

        return response()->json([
            'suggestions' => $this->serializeSuggestions($article),
        ]);
    }

    /**
     * Applica un suggerimento al body ricevuto dal client (contenuto
     * corrente di TinyMCE, non necessariamente salvato) e restituisce
     * l'HTML aggiornato. Non tocca mai il record Article e non marca il
     * suggerimento come accettato qui: quella decisione diventa definitiva
     * solo quando l'articolo viene davvero salvato (vedi
     * ArticleLinkSuggestionService::markAccepted, invocato da
     * Admin/Redazione ArticleController::update) — altrimenti un
     * inserimento seguito da un abbandono della modifica senza salvare
     * marcherebbe per sempre "gestito" un collegamento mai arrivato
     * nell'articolo.
     */
    public function insert(Request $request, Article $article, ArticleLinkSuggestion $suggestion): JsonResponse
    {
        $this->authorizeArticleAccess($article);
        $this->authorizeSuggestionOwnership($article, $suggestion);

        if (! $suggestion->isActionable()) {
            return response()->json([
                'message' => 'Questo suggerimento è già stato gestito.',
            ], 409);
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $targetUrl = route('articolo', $suggestion->targetArticle->slug);

        $updatedBody = $this->insertionService->insert($validated['body'], $suggestion->anchor_text, $targetUrl);

        if ($updatedBody === null) {
            return response()->json([
                'message' => 'La frase suggerita non è più presente nel testo (o si trova in un punto non modificabile, come un titolo o una citazione). Il testo potrebbe essere stato modificato: prova ad analizzare di nuovo i collegamenti.',
            ], 422);
        }

        return response()->json([
            'body' => $updatedBody,
        ]);
    }

    /**
     * Marca un suggerimento come ignorato: non verrà più riproposto (FASE 7).
     */
    public function ignore(Request $request, Article $article, ArticleLinkSuggestion $suggestion): JsonResponse
    {
        $this->authorizeArticleAccess($article);
        $this->authorizeSuggestionOwnership($article, $suggestion);

        if (! $suggestion->isActionable()) {
            return response()->json([
                'message' => 'Questo suggerimento è già stato gestito.',
            ], 409);
        }

        $suggestion->update([
            'status' => ArticleLinkSuggestion::STATUS_IGNORED,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json(['ignored' => true]);
    }

    private function authorizeArticleAccess(Article $article): void
    {
        $user = auth()->user();

        // Un collaboratore (author) vede/gestisce solo i propri articoli,
        // stessa regola già applicata in Redazione\ArticleController per
        // modifica/eliminazione. Editor/admin possono agire su qualunque
        // articolo.
        if (! $user->isEditor() && $article->user_id !== $user->id) {
            abort(403);
        }
    }

    private function authorizeSuggestionOwnership(Article $article, ArticleLinkSuggestion $suggestion): void
    {
        if ($suggestion->source_article_id !== $article->id) {
            abort(404);
        }
    }

    /**
     * V2.1: da quando il suggeritore può proporre anche un target ancora
     * 'scheduled' (temporalmente sicuro, vedi
     * ArticleLinkSuggestionService::analyzeForSource()), il pannello deve
     * mostrarlo chiaramente come tale — mai come se fosse già pubblico
     * senza contesto, per non confondere la redazione (FASE 5 della
     * missione V2.1). L'URL resta comunque quello reale dell'articolo: non
     * è raggiungibile pubblicamente finché non viene pubblicato, ma è lo
     * stesso URL che il link inserito nel body userà.
     */
    private function serializeSuggestions(Article $article): array
    {
        return $article->proposedLinkSuggestions()
            ->map(function (ArticleLinkSuggestion $s) {
                $target = $s->targetArticle;
                $isScheduled = $target->isScheduled() && $target->published_at !== null;

                return [
                    'id' => $s->id,
                    'anchor_text' => $s->anchor_text,
                    'context_excerpt' => $s->context_excerpt,
                    'reason' => $s->reason,
                    'confidence_score' => $s->confidence_score,
                    'target' => [
                        'id' => $target->id,
                        'title' => $target->title,
                        'url' => route('articolo', $target->slug),
                        'scheduled_label' => $isScheduled
                            ? 'Programmato per '.$target->publishedAtForEditors()->format('d/m/Y H:i').' — sarà pubblico prima di questo articolo'
                            : null,
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
