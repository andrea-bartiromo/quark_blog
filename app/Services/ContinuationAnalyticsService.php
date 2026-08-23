<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use Illuminate\Support\Facades\Log;

/**
 * Growth S2 — Second Read Analytics: misura quanti lettori iniziano
 * davvero una seconda lettura attraverso "Continua da qui" (Article A → B).
 *
 * Deliberatamente minimale rispetto al funnel completo
 * impression→click→arrival descritto nel brief: questa v1 registra solo
 * IMPRESSION e SECOND_READ_START. Il click è una metrica diagnostica, non
 * la KPI primaria (Second Read Rate = second reads / impressions non lo
 * richiede), e tracciarlo in modo sicuro richiederebbe un endpoint POST
 * dedicato con la propria superficie di validazione — costo/rischio non
 * giustificato per una metrica esplicitamente secondaria. Vedi il report
 * della missione per la decisione di scope completa.
 *
 * Nessun identificativo di visitatore persistito: la deduplicazione usa la
 * sessione Laravel già in uso da ArticleViewTrackingService per lo stesso
 * scopo (stesso pattern, stessa vita del cookie di sessione — nessuna
 * nuova infrastruttura di tracking introdotta).
 *
 * Traffico interno (redazione/admin autenticati) escluso riusando
 * ArticleViewTrackingService::shouldCountRequest(), la stessa regola già
 * applicata al conteggio delle view pubbliche — non una seconda
 * definizione di "traffico interno" da mantenere allineata a mano.
 *
 * Fail-open per design: un fallimento di scrittura qui non deve mai
 * impedire la lettura pubblica dell'articolo (vedi try/catch in
 * ArticleController::show()).
 */
class ContinuationAnalyticsService
{
    public function __construct(
        private readonly ArticleViewTrackingService $viewTracking
    ) {}

    public function recordImpression(Article $source, Article $target): void
    {
        $this->recordOnce(
            ArticleContinuationEvent::EVENT_IMPRESSION,
            $source,
            $target,
            'continuation_impression_'.$source->id.'_'.$target->id
        );
    }

    public function recordSecondReadStart(Article $source, Article $target): void
    {
        $this->recordOnce(
            ArticleContinuationEvent::EVENT_SECOND_READ_START,
            $source,
            $target,
            'continuation_second_read_'.$source->id.'_'.$target->id
        );
    }

    private function recordOnce(string $eventType, Article $source, Article $target, string $sessionKey): void
    {
        if (! $this->viewTracking->shouldCountRequest()) {
            return;
        }

        if (session()->has($sessionKey)) {
            return;
        }

        try {
            ArticleContinuationEvent::create([
                'event_type' => $eventType,
                'source_article_id' => $source->id,
                'target_article_id' => $target->id,
            ]);

            session()->put($sessionKey, true);
        } catch (\Throwable $exception) {
            Log::warning('ContinuationAnalyticsService: scrittura evento fallita, la lettura pubblica non è stata bloccata.', [
                'event_type' => $eventType,
                'source_article_id' => $source->id,
                'target_article_id' => $target->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{impressions:int,second_reads:int,second_read_rate:float}
     */
    public function statsFor(Article $source, ?\DateTimeInterface $since = null): array
    {
        $impressions = ArticleContinuationEvent::query()
            ->where('source_article_id', $source->id)
            ->where('event_type', ArticleContinuationEvent::EVENT_IMPRESSION)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->count();

        $secondReads = ArticleContinuationEvent::query()
            ->where('source_article_id', $source->id)
            ->where('event_type', ArticleContinuationEvent::EVENT_SECOND_READ_START)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->count();

        return [
            'impressions' => $impressions,
            'second_reads' => $secondReads,
            'second_read_rate' => $impressions > 0 ? round($secondReads / $impressions, 4) : 0.0,
        ];
    }
}
