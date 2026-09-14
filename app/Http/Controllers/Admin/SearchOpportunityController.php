<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportSearchConsoleCsvRequest;
use App\Models\SearchOpportunityDecision;
use App\Models\SearchOpportunityStatus;
use App\Services\SearchConsole\SearchConsoleCsvImporter;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use App\Services\SearchConsole\SearchConsoleImportCoverageService;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityDecisionService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use App\Services\SearchConsole\SearchOpportunityStatusService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class SearchOpportunityController extends Controller
{
    public function __construct(
        private readonly SearchOpportunityScoringService $scoring,
        private readonly SearchOpportunityStatusService $statuses,
        private readonly SearchConsoleFreshnessService $freshness,
        private readonly SearchConsoleImportCoverageService $coverage,
        private readonly SearchOpportunityDecisionService $decisions,
    ) {}

    public function index(Request $request): View
    {
        $periods = $this->freshness->availablePeriods();
        $typeOptions = $this->typeOptions();
        $statusOptions = SearchOpportunityStatus::statusOptions();

        $type = $request->input('tipo');
        if (! is_string($type) || ! array_key_exists($type, $typeOptions)) {
            $type = null;
        }

        // Missione 46 (Fase F — Search Intelligence): "opportunity
        // lifecycle filter" — lo stato è già impostabile per riga
        // (updateStatus()) e già mostrato, ma nessun filtro esisteva mai
        // per nasconderlo dall'elenco — un'opportunità già "gestita" o
        // "ignorata" restava sempre mescolata con quelle nuove. Stesso
        // identico pattern del filtro `tipo` già esistente qui sopra.
        $status = $request->input('stato');
        if (! is_string($status) || ! array_key_exists($status, $statusOptions)) {
            $status = null;
        }

        $latest = $periods->first();
        $opportunities = $this->scoring->currentOpportunities($latest, $periods->get(1));

        if ($type !== null) {
            $opportunities = $opportunities->filter(fn ($o) => $o->type === $type)->values();
        }

        // Una sola query per l'intero elenco (già filtrato per tipo), mai
        // una per riga — stesso principio già documentato da
        // SearchOpportunityStatusService::statusesFor().
        $opportunityStatuses = $this->statuses->statusesFor($opportunities);

        if ($status !== null) {
            $opportunities = $opportunities
                ->filter(fn ($o) => ($opportunityStatuses[$o->key] ?? SearchOpportunityStatus::STATUS_NEW) === $status)
                ->values();
        }

        // Cantiere 4 (programma "Kairus Organic Discovery"): decisione
        // editoriale corrente per opportunità — stesso principio bulk di
        // opportunityStatuses qui sopra, mai una query per riga.
        $opportunityDecisions = $this->decisions->decisionsFor($opportunities);

        return view('admin.search-opportunities.index', [
            'periods' => $periods,
            'selectedPeriod' => $latest,
            'opportunities' => $opportunities,
            'typeOptions' => $typeOptions,
            'selectedType' => $type,
            'statusOptions' => $statusOptions,
            'selectedStatus' => $status,
            'opportunityStatuses' => $opportunityStatuses,
            'opportunityDecisions' => $opportunityDecisions,
            'decisionTypeOptions' => SearchOpportunityDecision::decisionTypeOptions(),
            // Missione 45 (Fase F — Search Intelligence): "import
            // freshness" — cronologia dei singoli import CSV (già
            // idempotenti-per-periodo), mai mostrata finora, solo l'ultimo
            // periodo disponibile lo era tramite $periods sopra.
            'importHistory' => $this->freshness->importHistory(),
            // Cantiere 1 (programma "Kairus Organic Discovery"): copertura
            // effettiva per property/periodo/tipo di report — distinta
            // dalla cronologia grezza per singolo import qui sopra.
            'coverage' => $this->coverage->all(),
        ]);
    }

    /**
     * Cantiere 4 (programma "Kairus Organic Discovery"): registra la
     * decisione editoriale per un'opportunità — mai un'azione automatica.
     * L'opportunità viene ricalcolata dal periodo corrente (mai fidandosi
     * ciecamente dei soli campi nascosti del form): se non è più presente
     * (dati cambiati tra l'apertura della pagina e l'invio), fail-closed —
     * nessuna decisione registrata, nessun baseline indovinato.
     */
    public function recordDecision(Request $request): RedirectResponse
    {
        $decisionTypes = array_keys(SearchOpportunityDecision::decisionTypeOptions());

        $validated = $request->validate([
            // 600 non basta per ogni chiave valida: tipo (fino a ~28
            // caratteri) + query (255) + page_url (500) può superare 600
            // (Codex, PR #590) — opportunity_key è ora una colonna TEXT
            // (nessun limite di indicizzazione, l'unicità reale è
            // sull'hash), questo è solo un limite di sanità.
            'opportunity_key' => ['required', 'string', 'max:1200'],
            'decision_type' => ['required', 'string', 'in:'.implode(',', $decisionTypes)],
            'rationale' => [
                'nullable', 'string', 'max:2000',
                'required_if:decision_type,'.SearchOpportunityDecision::DECISION_MERGE,
                'required_if:decision_type,'.SearchOpportunityDecision::DECISION_IGNORE,
            ],
            'article_id' => [
                'nullable', 'integer', 'exists:articles,id',
                'required_if:decision_type,'.SearchOpportunityDecision::DECISION_UPDATE_ARTICLE,
                'required_if:decision_type,'.SearchOpportunityDecision::DECISION_MERGE,
            ],
        ]);

        $periods = $this->freshness->availablePeriods();
        $opportunity = $this->scoring->currentOpportunities($periods->first(), $periods->get(1))
            ->first(fn (SearchOpportunity $o) => $o->key === $validated['opportunity_key']);

        if ($opportunity === null) {
            return back()->withErrors(['opportunity_key' => 'Questa opportunità non è più disponibile nel periodo corrente: impossibile registrare la decisione.']);
        }

        // La creazione del brief (se richiesta) avviene DENTRO
        // SearchOpportunityDecisionService::record(), nella stessa
        // transazione con lock della decisione — mai qui separatamente,
        // altrimenti due invii concorrenti potrebbero creare due
        // ProjectTask distinti prima che uno dei due salvi la decisione
        // (Codex, PR #590).
        try {
            $this->decisions->record(
                $opportunity,
                $validated['decision_type'],
                $validated['rationale'] ?? null,
                $validated['article_id'] ?? null,
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['decision_type' => $e->getMessage()]);
        }

        return back()->with('status', 'Decisione registrata.');
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opportunity_key' => ['required', 'string', 'max:600'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(SearchOpportunityStatus::statusOptions()))],
        ]);

        $this->statuses->setStatus($validated['opportunity_key'], $validated['status'], $request->user());

        return back()->with('status', 'Stato aggiornato.');
    }

    public function importForm(): View
    {
        return view('admin.search-opportunities.import');
    }

    public function import(ImportSearchConsoleCsvRequest $request, SearchConsoleCsvImporter $importer): RedirectResponse
    {
        $result = $importer->import(
            $request->file('csv')->getRealPath(),
            Carbon::parse($request->input('period_start')),
            Carbon::parse($request->input('period_end')),
            $request->input('property'),
        );

        if ($result->imported === 0) {
            return back()->withErrors(['csv' => implode(' ', $result->errors) ?: 'Import fallito.']);
        }

        $message = "Import completato: {$result->imported} righe importate, {$result->matchedToArticle} collegate a un articolo.";

        if (! empty($result->errors)) {
            $message .= ' '.count($result->errors).' righe scartate.';
        }

        return redirect()->route('admin.search-opportunities')->with('status', $message);
    }

    private function typeOptions(): array
    {
        return [
            SearchOpportunityScoringService::TYPE_HIGH_IMPRESSION_LOW_CTR => 'Molte impression, CTR basso',
            SearchOpportunityScoringService::TYPE_GOOD_POSITION_LOW_CTR => 'Buona posizione, CTR basso',
            SearchOpportunityScoringService::TYPE_NEAR_PAGE_ONE => 'Vicino alla pagina 1',
            SearchOpportunityScoringService::TYPE_NO_STRONG_LANDING_PAGE => 'Nessuna landing page dedicata',
            SearchOpportunityScoringService::TYPE_RISING_QUERY => 'Query in crescita',
            SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH => 'Ricerca interna senza risultati',
            SearchOpportunityScoringService::TYPE_SEARCH_CANNIBALIZATION => 'Cannibalizzazione di ricerca',
        ];
    }
}
