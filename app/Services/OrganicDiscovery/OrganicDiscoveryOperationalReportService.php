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

        $dueButUnmeasured28d = SearchOpportunityDecision::query()
            ->whereNull('measured_28d_at')
            ->whereNotNull('baseline_captured_at')
            ->where('baseline_captured_at', '<=', now()->subDays(28))
            ->count();

        $dueButUnmeasured90d = SearchOpportunityDecision::query()
            ->whereNull('measured_90d_at')
            ->whereNotNull('baseline_captured_at')
            ->where('baseline_captured_at', '<=', now()->subDays(90))
            ->count();

        return [
            'total' => (int) $byType->sum(),
            'by_type' => collect(SearchOpportunityDecision::decisionTypeOptions())
                ->mapWithKeys(fn (string $label, string $type) => [
                    $type => ['label' => $label, 'count' => (int) ($byType[$type] ?? 0)],
                ])
                ->all(),
            'due_but_unmeasured_28d' => $dueButUnmeasured28d,
            'due_but_unmeasured_90d' => $dueButUnmeasured90d,
        ];
    }

    /** @return array<string, mixed> */
    private function outcomesSummary(): array
    {
        return [
            '28d' => $this->classifyOutcomes(
                SearchOpportunityDecision::query()
                    ->whereNotNull('measured_28d_at')
                    ->get(['baseline_clicks', 'measured_28d_clicks'])
                    ->map(fn (SearchOpportunityDecision $d) => [$d->baseline_clicks, $d->measured_28d_clicks])
            ),
            '90d' => $this->classifyOutcomes(
                SearchOpportunityDecision::query()
                    ->whereNotNull('measured_90d_at')
                    ->get(['baseline_clicks', 'measured_90d_clicks'])
                    ->map(fn (SearchOpportunityDecision $d) => [$d->baseline_clicks, $d->measured_90d_clicks])
            ),
        ];
    }

    /**
     * Classifica ogni decisione misurata confrontando i clic osservati con
     * il baseline catturato alla decisione — unica metrica scelta perché è
     * l'unica non ambigua da "migliorata/invariata/peggiorata" (CTR e
     * posizione dipendono anche da impression che possono variare per
     * ragioni indipendenti dalla decisione). Nessuna soglia di
     * significatività: un solo clic in più conta già come "migliorata",
     * dichiarato esplicitamente qui perché un redattore possa giudicare da
     * solo se il numero è abbastanza per contare come segnale.
     *
     * @param  Collection<int, array{0:int,1:int}>  $pairs  [baseline_clicks, measured_clicks]
     * @return array{measured:int, improved:int, flat:int, worse:int}
     */
    private function classifyOutcomes(Collection $pairs): array
    {
        $improved = $pairs->filter(fn (array $pair) => $pair[1] > $pair[0])->count();
        $worse = $pairs->filter(fn (array $pair) => $pair[1] < $pair[0])->count();
        $flat = $pairs->count() - $improved - $worse;

        return [
            'measured' => $pairs->count(),
            'improved' => $improved,
            'flat' => $flat,
            'worse' => $worse,
        ];
    }
}
