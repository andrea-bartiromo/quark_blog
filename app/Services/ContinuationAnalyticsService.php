<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use Illuminate\Support\Collection;
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
    public function statsFor(Article $source, ?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): array
    {
        $impressions = ArticleContinuationEvent::query()
            ->where('source_article_id', $source->id)
            ->where('event_type', ArticleContinuationEvent::EVENT_IMPRESSION)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->count();

        $secondReads = ArticleContinuationEvent::query()
            ->where('source_article_id', $source->id)
            ->where('event_type', ArticleContinuationEvent::EVENT_SECOND_READ_START)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->count();

        return [
            'impressions' => $impressions,
            'second_reads' => $secondReads,
            'second_read_rate' => $impressions > 0 ? round($secondReads / $impressions, 4) : 0.0,
        ];
    }

    /**
     * Totali sitewide nel periodo indicato — MAI sommare articleBreakdown()
     * per ottenere questo numero: quella lista è troncata a `limit` per la
     * visualizzazione, quindi una somma dei suoi valori sottostimerebbe i
     * totali reali non appena le sorgenti distinte superano il limite.
     * Qui invece due COUNT semplici, senza alcun raggruppamento per
     * articolo sorgente e quindi senza alcun tetto.
     *
     * @return array{impressions:int,second_reads:int,second_read_rate:float,source_articles_engaged:int}
     */
    public function siteWideTotals(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null): array
    {
        $impressions = ArticleContinuationEvent::query()
            ->where('event_type', ArticleContinuationEvent::EVENT_IMPRESSION)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->count();

        $secondReads = ArticleContinuationEvent::query()
            ->where('event_type', ArticleContinuationEvent::EVENT_SECOND_READ_START)
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->count();

        $sourceArticlesEngaged = ArticleContinuationEvent::query()
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->distinct('source_article_id')
            ->count('source_article_id');

        return [
            'impressions' => $impressions,
            'second_reads' => $secondReads,
            'second_read_rate' => $impressions > 0 ? round($secondReads / $impressions, 4) : 0.0,
            'source_articles_engaged' => $sourceArticlesEngaged,
        ];
    }

    /**
     * Riepilogo per articolo sorgente nel periodo indicato, ordinato per
     * second read decrescenti — il segnale editoriale che questa missione
     * chiede di rendere visibile (quali articoli avviano davvero una
     * seconda lettura, non solo quanti eventi grezzi esistono).
     *
     * Bounded by design: una query di aggregazione (GROUP BY) più una
     * query whereIn per i titoli — mai una query per articolo, quindi
     * nessun N+1 indipendentemente da quanti articoli sorgente esistano.
     *
     * @return Collection<int, array{source_article_id:int,title:?string,slug:?string,impressions:int,second_reads:int,second_read_rate:float}>
     */
    public function articleBreakdown(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null, int $limit = 50): Collection
    {
        $counts = ArticleContinuationEvent::query()
            ->selectRaw('source_article_id, event_type, COUNT(*) as total')
            ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('created_at', '<=', $until))
            ->groupBy('source_article_id', 'event_type')
            ->get();

        if ($counts->isEmpty()) {
            return collect();
        }

        $rows = $counts->groupBy('source_article_id')
            ->map(function ($eventRows, $sourceId) {
                $impressions = (int) ($eventRows->firstWhere('event_type', ArticleContinuationEvent::EVENT_IMPRESSION)->total ?? 0);
                $secondReads = (int) ($eventRows->firstWhere('event_type', ArticleContinuationEvent::EVENT_SECOND_READ_START)->total ?? 0);

                return [
                    'source_article_id' => (int) $sourceId,
                    'impressions' => $impressions,
                    'second_reads' => $secondReads,
                    'second_read_rate' => $impressions > 0 ? round($secondReads / $impressions, 4) : 0.0,
                ];
            })
            ->sortByDesc('second_reads')
            ->take($limit)
            ->values();

        $articles = Article::query()
            ->whereIn('id', $rows->pluck('source_article_id'))
            ->get(['id', 'title', 'slug'])
            ->keyBy('id');

        return $rows->map(function (array $row) use ($articles) {
            $article = $articles->get($row['source_article_id']);
            $row['title'] = $article?->title;
            $row['slug'] = $article?->slug;

            return $row;
        });
    }
}
