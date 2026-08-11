<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use App\Services\InternalLinking\InternalLinkTemporalEligibility;
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
        private readonly InternalLinkTemporalEligibility $temporalEligibility = new InternalLinkTemporalEligibility,
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

        // Codex (PR #165, round 13): target_article_id è nullOnDelete()
        // (round 12) — se il target viene eliminato tra il caricamento
        // della pagina di modifica e il click su "Inserisci", la riga resta
        // 'proposed' (actionable) ma targetArticle è null. Questo controllo
        // deve precedere isTargetSafeForSource() sotto: il suo parametro
        // $target è tipizzato Article non-nullable, quindi passargli null
        // produrrebbe comunque un TypeError/500 invece del 409 che questo
        // stesso metodo già restituisce per ogni altro suggerimento non più
        // utilizzabile.
        if ($suggestion->targetArticle === null) {
            $suggestion->update(['status' => ArticleLinkSuggestion::STATUS_SUPERSEDED]);

            return response()->json([
                'message' => 'L\'articolo di destinazione di questo suggerimento non esiste più. Analizza di nuovo i collegamenti.',
            ], 409);
        }

        // Codex (PR #165, P1): tra il momento in cui il suggerimento fu
        // calcolato (ultima "Analizza") e questo click su "Inserisci", il
        // target potrebbe essere stato riprogrammato DOPO questo articolo o
        // retrocesso a bozza/revisione — invariante non negoziabile della
        // missione ("mai un link a un target che sarà ancora non pubblico
        // quando la source uscirà"), quindi va riverificata qui, non solo
        // al momento di "Analizza". Il suggerimento viene marcato superato
        // (stesso stato usato altrove per un suggerimento non più valido),
        // non lasciato "proposed" per un click successivo che fallirebbe
        // di nuovo allo stesso modo.
        if (! $this->temporalEligibility->isTargetSafeForSource($article, $suggestion->targetArticle)) {
            $suggestion->update(['status' => ArticleLinkSuggestion::STATUS_SUPERSEDED]);

            return response()->json([
                'message' => 'Questo collegamento non è più valido: la programmazione di pubblicazione è cambiata. Analizza di nuovo i collegamenti interni.',
            ], 409);
        }

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $targetSlug = $suggestion->targetArticle->slug;
        $targetUrl = route('articolo', $targetSlug);

        $updatedBody = $this->insertionService->insert($validated['body'], $suggestion->anchor_text, $targetUrl);

        if ($updatedBody === null) {
            return response()->json([
                'message' => 'La frase suggerita non è più presente nel testo (o si trova in un punto non modificabile, come un titolo o una citazione). Il testo potrebbe essere stato modificato: prova ad analizzare di nuovo i collegamenti.',
            ], 422);
        }

        // Codex (PR #165, round 14 e round 17): target_slug è lo snapshot
        // preso all'ultima "Analizza" (vedi ArticleLinkSuggestionService) —
        // se il target viene rinominato dopo, ma prima di questo click su
        // "Inserisci", quello snapshot resta il vecchio slug mentre l'href
        // appena costruito usa (correttamente) lo slug ATTUALE. Lo snapshot
        // va riallineato allo slug realmente usato — ma SOLO ORA, dopo che
        // insert() ha confermato di aver davvero inserito il link (round
        // 17): se l'anchor non è più presente nel body (es. era già stato
        // avvolto da un link precedente verso il vecchio slug, ancora
        // presente), aggiornare lo snapshot PRIMA di questo controllo
        // avrebbe disallineato target_slug da quel link precedente
        // realmente ancora nel body, esattamente il problema che il round
        // 14 doveva risolvere.
        if ($suggestion->target_slug !== $targetSlug) {
            $suggestion->update(['target_slug' => $targetSlug]);
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
