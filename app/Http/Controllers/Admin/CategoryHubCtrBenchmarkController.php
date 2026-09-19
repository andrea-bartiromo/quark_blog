<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CategoryHubCtrBenchmarkService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cantiere 53 (programma "100 cantieri Kairus"): superficie admin di sola
 * lettura per il benchmark CTR hub categoria — stesso pattern di
 * Admin\SecondReadAnalyticsController (Growth S2). Non introduce alcuna
 * nuova decisione: mostra impression/click-through/CTR già calcolati da
 * CategoryHubCtrBenchmarkService.
 */
class CategoryHubCtrBenchmarkController extends Controller
{
    public function __construct(
        private readonly CategoryHubCtrBenchmarkService $benchmark,
    ) {}

    public function index(Request $request): View
    {
        $range = $this->resolveRange($request);

        return view('admin.category-hub-ctr-benchmark', [
            'breakdown' => $this->benchmark->hubBreakdown($range['since'], $range['until']),
            'totals' => $this->benchmark->siteWideTotals($range['since'], $range['until']),
            'rangeOption' => $range['option'],
        ]);
    }

    /**
     * @return array{option:string, since:?Carbon, until:?Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $option = $request->input('periodo', 'sempre');

        return match ($option) {
            '7' => ['option' => '7', 'since' => Carbon::now()->subDays(7), 'until' => null],
            '30' => ['option' => '30', 'since' => Carbon::now()->subDays(30), 'until' => null],
            '90' => ['option' => '90', 'since' => Carbon::now()->subDays(90), 'until' => null],
            default => ['option' => 'sempre', 'since' => null, 'until' => null],
        };
    }
}
