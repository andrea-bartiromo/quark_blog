<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchOpportunityDecision;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cantiere 5 (programma "Kairus Organic Discovery"): rilevatore di
 * cannibalizzazione di ricerca — articoli pubblici diversi che ricevono
 * impression Search Console REALI per la stessa query, non solo un
 * `primary_query` dichiarato uguale (quel controllo più leggero resta in
 * ArticleSearchProfileCollisionService/EditorialOpportunityDecisionService,
 * mai duplicato qui). Sola lettura: nessuna modifica automatica di
 * contenuto, collegamenti o pubblicazione. La risoluzione (fusione o
 * differenziazione editoriale) resta una decisione umana, registrata
 * tramite l'infrastruttura già esistente del Cantiere 4
 * (route admin.search-opportunities.record-decision, decision_type
 * "merge") — nessuna scrittura propria di questa pagina.
 */
class SearchCannibalizationController extends Controller
{
    public function __construct(
        private readonly SearchOpportunityScoringService $scoring,
        private readonly SearchConsoleFreshnessService $freshness,
        private readonly SearchOpportunityDecisionService $decisions,
    ) {}

    public function index(Request $request): View
    {
        $periods = $this->freshness->availablePeriods();
        $selected = $periods->first();

        $periodKey = $request->string('period')->toString();
        if ($periodKey !== '') {
            $selected = $periods->first(
                fn (array $period) => $periodKey === $period['period_start'].'|'.$period['period_end']
            ) ?? $selected;
        }

        $findings = $selected
            ? $this->scoring->cannibalizationFindingsForPeriod(
                Carbon::parse($selected['period_start']),
                Carbon::parse($selected['period_end']),
            )
            : collect();

        // Bulk-fetch, mai una query per riga — stesso principio già
        // documentato da SearchOpportunityStatusService::statusesFor() e
        // SearchOpportunityDecisionService::decisionsFor().
        $decisionsByKey = $this->decisions->decisionsFor($findings->map(fn ($finding) => $finding->opportunity));

        // SearchOpportunityController::recordDecision() ricalcola sempre
        // l'opportunità dal periodo PIÙ RECENTE (mai da quello selezionato
        // qui): un'opportunità di un periodo storico non esiste in quel
        // ricalcolo e la decisione fallirebbe sempre, con un modulo che
        // sembrava comunque disponibile (Codex, PR #592). Il modulo di
        // decisione compare quindi solo quando il periodo selezionato è
        // effettivamente quello più recente.
        $isLatestPeriod = $selected !== null && $periods->first() !== null
            && $selected['period_start'] === $periods->first()['period_start']
            && $selected['period_end'] === $periods->first()['period_end'];

        return view('admin.search-cannibalization.index', [
            'periods' => $periods,
            'selected' => $selected,
            'findings' => $findings,
            'decisionsByKey' => $decisionsByKey,
            'decisionTypeOptions' => SearchOpportunityDecision::decisionTypeOptions(),
            'isLatestPeriod' => $isLatestPeriod,
        ]);
    }
}
