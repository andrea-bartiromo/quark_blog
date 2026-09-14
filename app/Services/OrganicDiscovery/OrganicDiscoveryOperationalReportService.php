<?php

namespace App\Services\OrganicDiscovery;

use App\Models\SearchOpportunityDecision;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use App\Services\SearchConsole\SearchConsoleImportCoverageService;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cantiere 7 (programma "Kairus Organic Discovery"): un unico punto di
 * lettura periodico per lo stato del programma — mai una nuova regola,
 * mai un nuovo audit: compone esclusivamente i servizi già esistenti dei
 * Cantieri 1 (freschezza/copertura import), 3 (prontezza organica), 4
 * (decisioni/misurazione esiti) e 5 (cannibalizzazione), riusando le
 * stesse identità/soglie/stati già definiti altrove.
 *
 * Sola lettura, calcolata a ogni apertura della pagina — nessun comando
 * schedulato, nessuna email reale: stesso principio già scelto per ogni
 * altro cantiere di questo programma (vedi "Limiti dichiarati" in
 * docs/SEARCH_OPPORTUNITIES.md). "Report operativo settimanale" descrive
 * la cadenza attesa con cui un redattore apre questa pagina, non un job
 * automatico.
 */
class OrganicDiscoveryOperationalReportService
{
    public function __construct(
        private readonly SearchConsoleFreshnessService $freshness,
        private readonly SearchConsoleImportCoverageService $coverage,
        private readonly OrganicDiscoveryReadinessService $readiness,
        private readonly SearchOpportunityScoringService $scoring,
        private readonly SearchOpportunityDecisionService $decisions,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $periods = $this->freshness->availablePeriods();
        $latestPeriod = $periods->first();

        $currentOpportunities = $this->scoring->currentOpportunities($latestPeriod, $periods->get(1));
        $decisionsByKey = $this->decisions->decisionsFor($currentOpportunities);

        return [
            'search_console' => [
                'freshness' => $this->freshness->summary(),
                'coverage' => $this->coverageSummary(),
            ],
            'readiness' => $this->readinessSummary(),
            'opportunities' => [
                'current_total' => $currentOpportunities->count(),
                'current_with_decision' => $currentOpportunities
                    ->filter(fn ($opportunity) => array_key_exists($opportunity->key, $decisionsByKey))
                    ->count(),
                'current_without_decision' => $currentOpportunities
                    ->filter(fn ($opportunity) => ! array_key_exists($opportunity->key, $decisionsByKey))
                    ->count(),
            ],
            'decisions' => $this->decisionsSummary(),
            'outcomes' => $this->outcomesSummary(),
            'cannibalization' => [
                'current_period_findings' => $latestPeriod
                    ? $this->scoring->cannibalizationFindingsForPeriod(
                        Carbon::parse($latestPeriod['period_start']),
                        Carbon::parse($latestPeriod['period_end']),
                    )->count()
                    : 0,
            ],
        ];
    }

    /**
     * Numeri aggregati, mai la tabella riga-per-riga già mostrata per
     * intero in /admin/search-opportunities ("Copertura dati") — questa
     * pagina è un riepilogo, non una duplicazione di quella vista.
     *
     * @return array{periods_covered:int, total_rows_imported:int, total_unmatched_queries:int}
     */
    private function coverageSummary(): array
    {
        $rows = $this->coverage->all();

        return [
            'periods_covered' => $rows->count(),
            'total_rows_imported' => (int) $rows->sum('row_count'),
            'total_unmatched_queries' => (int) $rows->sum('unmatched_count'),
        ];
    }

    /** @return array<string, mixed> */
    private function readinessSummary(): array
    {
        $counts = $this->readiness->auditAll()->countBy('state');

        $states = [
            OrganicDiscoveryReadinessService::STATE_BLOCKED,
            OrganicDiscoveryReadinessService::STATE_NEEDS_WORK,
            OrganicDiscoveryReadinessService::STATE_READY,
            OrganicDiscoveryReadinessService::STATE_MEASURED,
        ];

        return [
            'total' => (int) $counts->sum(),
            'by_state' => collect($states)
                ->mapWithKeys(fn (string $state) => [
                    $state => [
                        'label' => OrganicDiscoveryReadinessService::stateLabel($state),
                        'count' => (int) ($counts[$state] ?? 0),
                    ],
                ])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function decisionsSummary(): array
    {
        $byType = SearchOpportunityDecision::query()
            ->selectRaw('decision_type, count(*) as total')
            ->groupBy('decision_type')
            ->pluck('total', 'decision_type');

        // "Dovute ma non misurate" non basta: measureDueOutcomes() misura
        // solo quelle per cui un periodo importato copre già l'orizzonte E
        // l'opportunità è ancora tra quelle attualmente calcolabili — un
        // conteggio che non distingue le due situazioni spingerebbe a
        // rilanciare il comando anche per righe che restano bloccate
        // finché non arriva un import più recente (Codex, PR #593).
        $eligibility = $this->decisions->dueOutcomesEligibility();

        return [
            'total' => (int) $byType->sum(),
            'by_type' => collect(SearchOpportunityDecision::decisionTypeOptions())
                ->mapWithKeys(fn (string $label, string $type) => [
                    $type => ['label' => $label, 'count' => (int) ($byType[$type] ?? 0)],
                ])
                ->all(),
            'runnable_28d' => $eligibility['runnable_28d'],
            'blocked_28d' => $eligibility['blocked_28d'],
            'runnable_90d' => $eligibility['runnable_90d'],
            'blocked_90d' => $eligibility['blocked_90d'],
        ];
    }

    /**
     * Due metriche distinte, mai una sola: le decisioni collegate a una
     * ricerca interna a zero risultati (TYPE_INTERNAL_ZERO_RESULT_SEARCH)
     * hanno `clicks`/`ctr` sempre nulli o azzerati per costruzione
     * (SearchOpportunityScoringService::internalZeroResultOpportunities()
     * — il segnale reale è il conteggio di ricerche in `impressions`), e
     * per quel tipo un valore più ALTO è un peggioramento, non un
     * miglioramento (più ricerche senza risultati, non meno) — mescolarle
     * con le opportunità "normali" le classificherebbe sempre come
     * "invariate" o con la direzione sbagliata (Codex, PR #593).
     *
     * Le opportunità "normali" confrontano il CTR osservato col baseline,
     * non i clic grezzi: baseline e misurazione possono provenire da
     * periodi Search Console di lunghezza diversa (l'importer non vincola
     * la durata), quindi un totale di clic più alto può riflettere solo
     * un periodo più lungo, non un miglioramento reale — il CTR è un
     * tasso, già indipendente dalla lunghezza del periodo (Codex, PR #593).
     *
     * @return array<string, mixed>
     */
    private function outcomesSummary(): array
    {
        return [
            '28d' => $this->classifyOutcomes($this->ctrPairs('measured_28d_at', 'measured_28d_ctr')),
            '90d' => $this->classifyOutcomes($this->ctrPairs('measured_90d_at', 'measured_90d_ctr')),
            'internal_zero_result_search' => [
                '28d' => $this->classifyOutcomes(
                    $this->internalZeroResultPairs('measured_28d_at', 'measured_28d_impressions'),
                    higherIsBetter: false,
                ),
                '90d' => $this->classifyOutcomes(
                    $this->internalZeroResultPairs('measured_90d_at', 'measured_90d_impressions'),
                    higherIsBetter: false,
                ),
            ],
        ];
    }

    /** @return Collection<int, array{0:float,1:float}> [baseline_ctr, measured_ctr] */
    private function ctrPairs(string $measuredAtColumn, string $measuredCtrColumn): Collection
    {
        return SearchOpportunityDecision::query()
            ->whereNotNull($measuredAtColumn)
            ->whereNotNull('baseline_ctr')
            ->whereNotNull($measuredCtrColumn)
            ->where('opportunity_type', '!=', SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH)
            ->get(['baseline_ctr', $measuredCtrColumn])
            ->map(fn (SearchOpportunityDecision $d) => [(float) $d->baseline_ctr, (float) $d->{$measuredCtrColumn}]);
    }

    /** @return Collection<int, array{0:int,1:int}> [baseline_impressions, measured_impressions] — hit_count delle ricerche interne senza risultati */
    private function internalZeroResultPairs(string $measuredAtColumn, string $measuredImpressionsColumn): Collection
    {
        return SearchOpportunityDecision::query()
            ->whereNotNull($measuredAtColumn)
            ->where('opportunity_type', SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH)
            ->get(['baseline_impressions', $measuredImpressionsColumn])
            ->map(fn (SearchOpportunityDecision $d) => [(int) $d->baseline_impressions, (int) $d->{$measuredImpressionsColumn}]);
    }

    /**
     * Nessuna soglia di significatività: una sola unità di differenza
     * conta già come "migliorata"/"peggiorata", dichiarato esplicitamente
     * perché un redattore possa giudicare da solo se il numero è
     * abbastanza per contare come segnale.
     *
     * @param  Collection<int, array{0:float|int,1:float|int}>  $pairs  [baseline, misurato]
     * @return array{measured:int, improved:int, flat:int, worse:int}
     */
    private function classifyOutcomes(Collection $pairs, bool $higherIsBetter = true): array
    {
        $improved = $pairs->filter(fn (array $pair) => $higherIsBetter ? $pair[1] > $pair[0] : $pair[1] < $pair[0])->count();
        $worse = $pairs->filter(fn (array $pair) => $higherIsBetter ? $pair[1] < $pair[0] : $pair[1] > $pair[0])->count();
        $flat = $pairs->count() - $improved - $worse;

        return [
            'measured' => $pairs->count(),
            'improved' => $improved,
            'flat' => $flat,
            'worse' => $worse,
        ];
    }
}
