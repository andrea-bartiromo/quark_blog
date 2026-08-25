<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportSearchConsoleCsvRequest;
use App\Models\SearchConsoleQuery;
use App\Models\SearchOpportunityStatus;
use App\Services\SearchConsole\SearchConsoleCsvImporter;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use App\Services\SearchConsole\SearchOpportunityStatusService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SearchOpportunityController extends Controller
{
    public function __construct(
        private readonly SearchOpportunityScoringService $scoring,
        private readonly SearchOpportunityStatusService $statuses,
        private readonly SearchConsoleFreshnessService $freshness,
    ) {}

    public function index(Request $request): View
    {
        $periods = $this->availablePeriods();
        $typeOptions = $this->typeOptions();

        $type = $request->input('tipo');
        if (! is_string($type) || ! array_key_exists($type, $typeOptions)) {
            $type = null;
        }

        $latest = $periods->first();
        $previous = $periods->get(1);

        // Mission 32: le opportunità da ricerca interna a zero risultati
        // (search_zero_result_queries, Missione 31) non dipendono da un
        // periodo Search Console — restano visibili anche senza alcun CSV
        // mai importato, quindi si calcolano sempre, non solo dentro il
        // ramo "periodi disponibili".
        $opportunities = $latest
            ? $this->scoring->forPeriod(
                Carbon::parse($latest['period_start']),
                Carbon::parse($latest['period_end']),
                $previous ? Carbon::parse($previous['period_start']) : null,
                $previous ? Carbon::parse($previous['period_end']) : null,
            )
            : collect();

        $opportunities = $opportunities
            ->merge($this->scoring->internalZeroResultOpportunities($opportunities))
            ->sortByDesc(fn ($o) => $o->score)
            ->values();

        if ($type !== null) {
            $opportunities = $opportunities->filter(fn ($o) => $o->type === $type)->values();
        }

        return view('admin.search-opportunities.index', [
            'periods' => $periods,
            'selectedPeriod' => $latest,
            'opportunities' => $opportunities,
            'typeOptions' => $typeOptions,
            'selectedType' => $type,
            'statusOptions' => SearchOpportunityStatus::statusOptions(),
            'opportunityStatuses' => $this->statuses->statusesFor($opportunities),
            // Missione 45 (Fase F — Search Intelligence): "import
            // freshness" — cronologia dei singoli import CSV (già
            // idempotenti-per-periodo), mai mostrata finora, solo l'ultimo
            // periodo disponibile lo era tramite $periods sopra.
            'importHistory' => $this->freshness->importHistory(),
        ]);
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

    /**
     * @return Collection<int, array{period_start:string,period_end:string}>
     */
    private function availablePeriods()
    {
        return SearchConsoleQuery::query()
            ->selectRaw('period_start, period_end')
            ->distinct()
            ->orderByDesc('period_start')
            ->get()
            ->map(fn ($row) => [
                'period_start' => $row->period_start,
                'period_end' => $row->period_end,
            ]);
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
        ];
    }
}
