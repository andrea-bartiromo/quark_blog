<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleContinuationEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
    public function articleBreakdown(
        ?\DateTimeInterface $since = null,
        ?\DateTimeInterface $until = null,
        int $limit = 50,
        ?callable $filter = null
    ): Collection
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
            });

        if ($filter !== null) {
            $rows = $rows->filter($filter);
        }

        $rows = $rows
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

    /**
     * Riepilogo per Percorso basato sulla relazione corrente tra articolo
     * sorgente e content cluster. Gli eventi non salvano il contesto del
     * Percorso: se un articolo appartiene a piu Percorsi, il suo segnale e
     * attribuito a ciascuno di essi e le righe non devono essere sommate.
     *
     * @return Collection<int, array{content_cluster_id:int,name:string,slug:string,impressions:int,second_reads:int,second_read_rate:float,source_articles_engaged:int}>
     */
    public function pathBreakdown(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null, int $limit = 50): Collection
    {
        $limit = max(1, min($limit, 100));

        return DB::table('article_continuation_events as events')
            ->join('article_content_cluster as membership', 'membership.article_id', '=', 'events.source_article_id')
            ->join('content_clusters as clusters', 'clusters.id', '=', 'membership.content_cluster_id')
            ->select(['clusters.id as content_cluster_id', 'clusters.name', 'clusters.slug'])
            ->selectRaw('SUM(CASE WHEN events.event_type = ? THEN 1 ELSE 0 END) as impressions', [ArticleContinuationEvent::EVENT_IMPRESSION])
            ->selectRaw('SUM(CASE WHEN events.event_type = ? THEN 1 ELSE 0 END) as second_reads', [ArticleContinuationEvent::EVENT_SECOND_READ_START])
            ->selectRaw('COUNT(DISTINCT events.source_article_id) as source_articles_engaged')
            ->when($since, fn ($query) => $query->where('events.created_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('events.created_at', '<=', $until))
            ->groupBy('clusters.id', 'clusters.name', 'clusters.slug')
            ->orderByDesc('second_reads')
            ->orderByDesc('impressions')
            ->orderBy('clusters.id')
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $impressions = (int) $row->impressions;
                $secondReads = (int) $row->second_reads;

                return [
                    'content_cluster_id' => (int) $row->content_cluster_id,
                    'name' => (string) $row->name,
                    'slug' => (string) $row->slug,
                    'impressions' => $impressions,
                    'second_reads' => $secondReads,
                    'second_read_rate' => $impressions > 0 ? round($secondReads / $impressions, 4) : 0.0,
                    'source_articles_engaged' => (int) $row->source_articles_engaged,
                ];
            });
    }
}
