<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrganicDiscovery\EditorialOpportunityDecisionService;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditorialOpportunityDecisionController extends Controller
{
    public function __construct(
        private readonly EditorialOpportunityDecisionService $decisions,
        private readonly SearchOpportunityScoringService $scoring,
        private readonly SearchConsoleFreshnessService $freshness,
        private readonly SearchOpportunityDecisionService $searchOpportunityDecisions,
    ) {}

    public function index(Request $request): View
    {
        $periods = $this->freshness->availablePeriods();
        $selected = $periods->first();
        $periodKey = $request->string('period')->toString();
        if ($periodKey !== '') {
            $selected = $periods->first(fn (array $period) => $periodKey === $period['period_start'].'|'.$period['period_end']) ?? $selected;
        }

        // decide() esegue OrganicDiscoveryReadinessService::auditAll() (audit
        // dell'intero corpus di articoli pubblici) all'inizio, indipendente
        // da quante opportunità gli vengono passate — mai chiamarlo quando
        // non c'è alcun periodo selezionato (nessun import Search Console),
        // altrimenti ogni apertura della pagina pagherebbe quell'audit
        // costoso solo per mostrare "Nessun import disponibile" (Codex, PR
        // #600).
        $opportunities = $selected
            ? $this->scoring->forPeriod(Carbon::parse($selected['period_start']), Carbon::parse($selected['period_end']))
            : collect();
        $rows = $selected ? $this->decisions->decide($opportunities) : collect();

        // Cantiere UX "Collegamento diretto tra Decisioni SEO e Opportunità
        // di ricerca": decisioni umane già registrate per queste stesse
        // opportunità (fonte di verità SearchOpportunityDecision, mai
        // ricalcolata qui) — stesso metodo bulk già usato da
        // SearchOpportunityController::index(), mai una query per riga.
        $searchOpportunityDecisions = $opportunities->isNotEmpty()
            ? $this->searchOpportunityDecisions->decisionsFor($opportunities)
            : [];

        // Vero solo quando il periodo selezionato in questa dashboard È
        // quello che "Opportunità di ricerca" mostrerebbe di default (quella
        // pagina non ha un selettore di periodo, mostra sempre l'ultimo
        // importato) — usato dalla vista per essere onesta quando il
        // collegamento non può riprodurre esattamente lo stesso periodo.
        $isCurrentPeriod = $selected && $periods->first()
            && $selected['period_start'] === $periods->first()['period_start']
            && $selected['period_end'] === $periods->first()['period_end'];

        $decision = $request->string('decision')->toString();
        if (! array_key_exists($decision, EditorialOpportunityDecisionService::labels())) {
            $decision = '';
        }
        if ($decision !== '') {
            $rows = $rows->where('decision', $decision)->values();
        }

        $counts = collect(array_keys(EditorialOpportunityDecisionService::labels()))
            ->mapWithKeys(fn (string $state) => [$state => $rows->where('decision', $state)->count()]);

        return view('admin.editorial-opportunity-decisions.index', compact('periods', 'selected', 'rows', 'decision', 'counts', 'searchOpportunityDecisions', 'isCurrentPeriod'));
    }
}
