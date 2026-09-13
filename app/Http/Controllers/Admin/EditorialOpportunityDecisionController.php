<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrganicDiscovery\EditorialOpportunityDecisionService;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
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
    ) {}

    public function index(Request $request): View
    {
        $periods = $this->freshness->availablePeriods();
        $selected = $periods->first();
        $periodKey = $request->string('period')->toString();
        if ($periodKey !== '') $selected = $periods->first(fn (array $period) => $period['period_start'].'|'.$period['period_end'] === $periodKey) ?? $selected;

        $rows = $selected
            ? $this->decisions->decide($this->scoring->forPeriod(Carbon::parse($selected['period_start']), Carbon::parse($selected['period_end'])))
            : collect();

        $decision = $request->string('decision')->toString();
        if (! array_key_exists($decision, EditorialOpportunityDecisionService::labels())) $decision = '';
        if ($decision !== '') $rows = $rows->where('decision', $decision)->values();

        $counts = collect(array_keys(EditorialOpportunityDecisionService::labels()))
            ->mapWithKeys(fn (string $state) => [$state => $rows->where('decision', $state)->count()]);

        return view('admin.editorial-opportunity-decisions.index', compact('periods', 'selected', 'rows', 'decision', 'counts'));
    }
}
