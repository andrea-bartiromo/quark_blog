<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SearchConsole\SearchConsoleBaselineReportService;
use App\Services\SearchConsole\SearchConsoleFreshnessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cantiere 1 (programma "Kairus Organic Discovery"). Pagina read-only:
 * totali clic/impression/CTR/posizione, top landing page, query non-brand
 * e le opportunità già scorate da SearchOpportunityScoringService per un
 * periodo Search Console selezionabile — mai il solo ultimo periodo come
 * in admin.search-opportunities.
 */
class SearchConsoleBaselineReportController extends Controller
{
    public function __construct(
        private readonly SearchConsoleFreshnessService $freshness,
        private readonly SearchConsoleBaselineReportService $reportService,
    ) {}

    public function index(Request $request): View
    {
        $periods = $this->freshness->availablePeriods();

        $requestedIndex = (int) $request->input('periodo', 0);
        $selectedIndex = $periods->has($requestedIndex) ? $requestedIndex : 0;
        $selected = $periods->get($selectedIndex);

        $report = $selected
            ? $this->reportService->report(
                Carbon::parse($selected['period_start']),
                Carbon::parse($selected['period_end']),
            )
            : null;

        return view('admin.search-console-baseline-report.index', [
            'periods' => $periods,
            'selectedIndex' => $selected ? $selectedIndex : null,
            'report' => $report,
        ]);
    }
}
