<?php

namespace App\Services\Telemetry;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\EditorialContinuityEvent;
use App\Services\ArticleViewTrackingService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Measurement Closeout (Missione 2) — unico producer di
 * editorial_continuity_events.
 *
 * Tre garanzie, in quest'ordine:
 *
 * 1. FAIL-SAFE. Nessun metodo pubblico di questa classe può propagare
 *    un'eccezione al chiamante. La telemetria è una funzione accessoria: se
 *    la scrittura fallisce, la pagina pubblica deve caricarsi lo stesso. Il
 *    try/catch è QUI e non solo nel controller, così la garanzia vale per
 *    qualunque futuro call site senza doversi ricordare di riavvolgerlo.
 *
 * 2. FAIL-CLOSED sui dati. Ogni payload passa da
 *    EditorialEventContract::validate() prima di toccare il database. Un
 *    payload non conforme viene rifiutato (e l'errore assorbito dal punto 1),
 *    mai "salvato al meglio".
 *
 * 3. STESSA definizione di traffico interno già in uso. La regola di
 *    ammissione è ArticleViewTrackingService::shouldCountRequest(), la stessa
 *    che governa il conteggio delle view pubbliche e gli eventi Growth S2 —
 *    mai una seconda definizione di "traffico redazionale" da tenere
 *    allineata a mano.
 *
 * DEDUPLICAZIONE. Ogni evento è idempotente per (sessione, evento, contesto)
 * tramite una chiave di sessione, esattamente come già fa
 * ContinuationAnalyticsService: un reload della stessa pagina nella stessa
 * sessione non deve contare due volte, altrimenti sia il numeratore sia il
 * denominatore di Missione 3 e 4 diventerebbero misure di "quante volte il
 * lettore ha premuto F5".
 */
class EditorialContinuityRecorder
{
    public function __construct(
        private readonly ArticleViewTrackingService $viewTracking,
        private readonly ContinuitySessionKey $sessionKey,
        private readonly SourceChannelResolver $sourceResolver,
    ) {}

    public function recordArticleView(Article $article, ?ContentCluster $cluster = null, ?int $position = null): void
    {
        $this->record(
            EditorialEventContract::ARTICLE_VIEWED,
            'ece_article_viewed_'.$article->id,
            [
                'article_id' => $article->id,
                'content_cluster_id' => $cluster?->id,
                'context_position' => $position,
            ]
        );
    }

    public function recordPathView(ContentCluster $cluster): void
    {
        $this->record(
            EditorialEventContract::PATH_VIEWED,
            'ece_path_viewed_'.$cluster->id,
            ['content_cluster_id' => $cluster->id]
        );
    }

    /**
     * Registra che un controllo di transizione era DAVVERO renderizzato sulla
     * pagina. È il denominatore di Missione 4: senza questo evento la
     * continuation rate resterebbe calcolabile solo su "tutte le pageview",
     * che è il denominatore sbagliato esplicitamente vietato dalla missione.
     */
    public function recordTransitionAvailable(
        Article $article,
        Article $target,
        string $transitionType,
        ?ContentCluster $cluster = null,
        ?int $position = null,
    ): void {
        $this->record(
            EditorialEventContract::TRANSITION_AVAILABLE,
            'ece_transition_'.$transitionType.'_'.$article->id.'_'.$target->id,
            [
                'article_id' => $article->id,
                'target_article_id' => $target->id,
                'transition_type' => $transitionType,
                'content_cluster_id' => $cluster?->id,
                'context_position' => $position,
            ]
        );
    }

    /**
     * Registra che un link verso un articolo era disponibile nell'indice di
     * un Percorso (pillar o tappa). Stesso ruolo di denominatore del metodo
     * sopra, per le metriche "clic sul pillar" / "clic sugli articoli del
     * Percorso".
     */
    public function recordPathLinkAvailable(
        ContentCluster $cluster,
        Article $target,
        string $transitionType,
        ?int $position = null,
    ): void {
        $this->record(
            EditorialEventContract::PATH_LINK_AVAILABLE,
            'ece_path_link_'.$transitionType.'_'.$cluster->id.'_'.$target->id,
            [
                'target_article_id' => $target->id,
                'transition_type' => $transitionType,
                'content_cluster_id' => $cluster->id,
                'context_position' => $position,
            ]
        );
    }

    /**
     * Un'iscrizione Newsletter avvenuta in questa sessione. Deliberatamente
     * privo di qualunque riferimento all'iscritto: la metrica editoriale
     * ("quante sessioni, e da quale sorgente, convertono") non ha bisogno di
     * sapere CHI si è iscritto, e la tabella comm_subscribers/newsletter
     * resta l'unica sede di quel dato.
     */
    public function recordNewsletterSubscription(?Article $article = null): void
    {
        $this->record(
            EditorialEventContract::NEWSLETTER_SUBSCRIBED,
            'ece_newsletter_subscribed',
            ['article_id' => $article?->id]
        );
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function record(string $eventName, string $dedupeKey, array $fields): void
    {
        try {
            if (! $this->viewTracking->shouldCountRequest()) {
                return;
            }

            $sessionKey = $this->sessionKey->forCurrentRequest();

            if ($sessionKey === null) {
                // Nessuna sessione utilizzabile: NON si inventa un
                // identificatore. Vedi ContinuitySessionKey — una sessione
                // sintetica per richiesta gonfierebbe il denominatore di
                // Missione 3 rendendo ogni pageview una visita distinta.
                return;
            }

            if (session()->has($dedupeKey)) {
                return;
            }

            $payload = EditorialEventContract::validate([
                'event_name' => $eventName,
                'schema_version' => EditorialEventContract::SCHEMA_VERSION,
                'session_key' => $sessionKey,
                'article_id' => $fields['article_id'] ?? null,
                'target_article_id' => $fields['target_article_id'] ?? null,
                'content_cluster_id' => $fields['content_cluster_id'] ?? null,
                'transition_type' => $fields['transition_type'] ?? null,
                'source_channel' => $this->sourceResolver->resolve(request()),
                'context_position' => $fields['context_position'] ?? null,
                'occurred_at' => now(),
            ]);

            EditorialContinuityEvent::create($payload);

            session()->put($dedupeKey, true);
        } catch (Throwable $exception) {
            // Il log riporta il NOME dell'evento e il messaggio
            // dell'eccezione, mai il payload: il payload contiene il
            // session_key pseudonimo, che non ha ragione di comparire nei
            // log applicativi.
            Log::warning('EditorialContinuityRecorder: evento non registrato, la navigazione pubblica non è stata interrotta.', [
                'event_name' => $eventName,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
