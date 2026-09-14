<?php

namespace App\Services\OrganicDiscovery;

use App\Models\Article;
use App\Models\ArticleSearchProfile;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\SearchConsoleQuery;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Illuminate\Support\Collection;

/**
 * Cantiere 8 (programma "Kairus Organic Discovery"): per ogni Percorso
 * (ContentCluster) o Concetto pubblico, confronta la domanda di ricerca
 * REALE già osservata (Search Console, Cantiere 1) con la copertura
 * editoriale già misurata altrove (OrganicDiscoveryReadinessService,
 * Cantiere 3) — mai una nuova regola di prontezza, mai un nuovo audit
 * strutturale: questo servizio compone soltanto, non ricalcola.
 *
 * Deliberatamente NON duplica nulla di già esistente: l'integrità
 * strutturale/di sequenza di un Percorso resta di
 * PercorsoCoverageAuditService, l'integrità del grafo dei Concetti
 * (orfani/alias/domande) resta di ContentGraphOperationalSummaryService/
 * ContentGraphOrphanAuditService, la prontezza per singolo articolo resta
 * di OrganicDiscoveryReadinessService — qui si legge solo il loro esito
 * già calcolato, incrociato con la domanda Search Console.
 *
 * Fail-closed per costruzione: solo articoli effettivamente pubblici
 * (Article::scopePublished() / predicato pubblico esplicito) entrano nel
 * conteggio, solo Percorsi pubblicamente raggiungibili
 * (ContentCluster::scopePubliclyVisible()) e Concetti attivi vengono
 * auditati — mai una bozza o un Percorso non ancora pubblico esposto qui.
 * Mai "nessuna domanda": quando non c'è un periodo Search Console
 * importato o le impression sono sotto soglia, lo stato è
 * "domanda non osservata", non "domanda assente" — il programma non
 * inventa un segnale che i dati non possono provare.
 */
class TopicalAuthorityGapService
{
    /**
     * Nessun articolo pubblico collegato: nulla da valutare finché la
     * redazione non pubblica almeno un articolo per questo Percorso/Concetto.
     */
    public const STATE_NO_PUBLIC_CONTENT = 'no_public_content';

    /**
     * C'è contenuto pubblico ma le impression osservate nell'ultimo
     * periodo importato sono sotto la soglia minima di evidenza
     * (SearchOpportunityScoringService::MIN_IMPRESSIONS) o nessun periodo
     * è mai stato importato — mai "nessuna domanda", solo "non osservata".
     */
    public const STATE_NO_DEMAND_OBSERVED = 'no_demand_observed';

    /**
     * Domanda osservata, ma almeno un articolo del gruppo non è ancora
     * "pronto" o "misurato" secondo OrganicDiscoveryReadinessService —
     * il gap non è di contenuto mancante, ma di qualità/prontezza non
     * ancora completata su un argomento che sta già ricevendo traffico.
     */
    public const STATE_GAP_WEAK_READINESS = 'gap_weak_readiness';

    /**
     * Domanda osservata e tutti gli articoli del gruppo sono "pronti" o
     * "misurati" — nessuna azione editoriale suggerita da questo segnale.
     */
    public const STATE_COVERED = 'covered';

    public function __construct(
        private readonly OrganicDiscoveryReadinessService $readiness,
        private readonly SearchConsoleFreshnessService $freshness,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function auditClusters(): Collection
    {
        $clusters = ContentCluster::query()
            ->publiclyVisible()
            ->with(['articles' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        $groups = $clusters->map(fn (ContentCluster $cluster) => [
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'article_ids' => $cluster->articles->pluck('id')->values(),
        ]);

        return $this->audit($groups);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function auditConcepts(): Collection
    {
        $concepts = Concept::query()
            ->active()
            ->with(['articleLinks.article'])
            ->orderBy('name')
            ->get();

        $groups = $concepts->map(function (Concept $concept) {
            $articleIds = $concept->articleLinks
                ->pluck('article')
                ->filter(fn (?Article $article) => $article !== null
                    && $article->status === Article::STATUS_PUBLISHED
                    && $article->published_at?->isPast())
                ->pluck('id')
                ->unique()
                ->values();

            return ['name' => $concept->name, 'slug' => $concept->slug, 'article_ids' => $articleIds];
        });

        return $this->audit($groups);
    }

    /**
     * Un'unica lettura bulk di readiness/domanda/query secondarie per
     * TUTTI i gruppi insieme — mai una query per Percorso/Concetto, stesso
     * principio già stabilito da SearchOpportunityStatusService::statusesFor()
     * e riusato in ogni cantiere di questo programma.
     *
     * @param  Collection<int, array{name:string, slug:string, article_ids: Collection<int,int>}>  $groups
     * @return Collection<int, array<string, mixed>>
     */
    private function audit(Collection $groups): Collection
    {
        $readinessByArticleId = $this->readiness->auditAll()->keyBy('article_id');
        $latestPeriod = $this->freshness->availablePeriods()->first();
        $allArticleIds = $groups->flatMap(fn (array $g) => $g['article_ids'])->unique()->values();

        $demandByArticleId = $this->demandByArticleId($allArticleIds, $latestPeriod);
        $secondaryQueriesByArticleId = $this->secondaryQueriesByArticleId($allArticleIds);

        return $groups->map(function (array $group) use ($readinessByArticleId, $demandByArticleId, $secondaryQueriesByArticleId) {
            $articleIds = $group['article_ids'];

            if ($articleIds->isEmpty()) {
                return [
                    'name' => $group['name'],
                    'slug' => $group['slug'],
                    'article_count' => 0,
                    'state' => self::STATE_NO_PUBLIC_CONTENT,
                    'impressions' => 0,
                    'clicks' => 0,
                    'readiness_counts' => [],
                    'secondary_queries' => [],
                ];
            }

            $impressions = (int) $articleIds->sum(fn (int $id) => $demandByArticleId[$id]['impressions'] ?? 0);
            $clicks = (int) $articleIds->sum(fn (int $id) => $demandByArticleId[$id]['clicks'] ?? 0);

            $readinessStates = $articleIds
                ->map(fn (int $id) => $readinessByArticleId[$id]['state'] ?? null)
                ->filter();
            $readinessCounts = $readinessStates->countBy()->all();

            $hasDemand = $impressions >= SearchOpportunityScoringService::MIN_IMPRESSIONS;
            $allReadyOrMeasured = $readinessStates->isNotEmpty() && $readinessStates->every(
                fn (string $state) => in_array($state, [
                    OrganicDiscoveryReadinessService::STATE_READY,
                    OrganicDiscoveryReadinessService::STATE_MEASURED,
                ], true)
            );

            $state = match (true) {
                ! $hasDemand => self::STATE_NO_DEMAND_OBSERVED,
                $allReadyOrMeasured => self::STATE_COVERED,
                default => self::STATE_GAP_WEAK_READINESS,
            };

            $secondaryQueries = $articleIds
                ->flatMap(fn (int $id) => $secondaryQueriesByArticleId[$id] ?? [])
                ->unique()
                ->values()
                ->all();

            return [
                'name' => $group['name'],
                'slug' => $group['slug'],
                'article_count' => $articleIds->count(),
                'state' => $state,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'readiness_counts' => $readinessCounts,
                'secondary_queries' => $secondaryQueries,
            ];
        });
    }

    /** @param  Collection<int, int>  $articleIds
     * @return Collection<int, array{impressions:int, clicks:int}> keyed by article_id */
    private function demandByArticleId(Collection $articleIds, ?array $latestPeriod): Collection
    {
        if ($articleIds->isEmpty() || $latestPeriod === null) {
            return collect();
        }

        return SearchConsoleQuery::query()
            ->whereIn('article_id', $articleIds)
            ->whereDate('period_start', $latestPeriod['period_start'])
            ->whereDate('period_end', $latestPeriod['period_end'])
            ->selectRaw('article_id, sum(impressions) as impressions, sum(clicks) as clicks')
            ->groupBy('article_id')
            ->get()
            ->keyBy('article_id')
            ->map(fn (SearchConsoleQuery $row) => ['impressions' => (int) $row->impressions, 'clicks' => (int) $row->clicks]);
    }

    /**
     * Primo consumatore reale di ArticleSearchProfile::secondary_queries
     * (Cantiere 2) — finora dichiarato in redazione ma mai letto da alcun
     * servizio: qui diventa il segnale "quali intenti di ricerca aggiuntivi
     * la redazione ha già dichiarato per questo argomento".
     *
     * @param  Collection<int, int>  $articleIds
     * @return Collection<int, array<int, string>> keyed by article_id
     */
    private function secondaryQueriesByArticleId(Collection $articleIds): Collection
    {
        if ($articleIds->isEmpty()) {
            return collect();
        }

        return ArticleSearchProfile::query()
            ->whereIn('article_id', $articleIds)
            ->whereNotNull('secondary_queries')
            ->get(['article_id', 'secondary_queries'])
            ->keyBy('article_id')
            ->map(fn (ArticleSearchProfile $profile) => $profile->secondary_queries ?? []);
    }

    /** @return array<string, string> */
    public static function stateLabels(): array
    {
        return [
            self::STATE_NO_PUBLIC_CONTENT => 'Nessun contenuto pubblico',
            self::STATE_NO_DEMAND_OBSERVED => 'Domanda non osservata',
            self::STATE_GAP_WEAK_READINESS => 'Domanda osservata, prontezza incompleta',
            self::STATE_COVERED => 'Coperto',
        ];
    }
}
