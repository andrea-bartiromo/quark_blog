<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ContinuationAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Growth S2 — Second Read Analytics V2: superficie admin di sola lettura
 * per il segnale già raccolto da ContinuationAnalyticsService. Non
 * introduce alcun nuovo tracking: aggrega e mostra ciò che
 * article_continuation_events registra già in modo privacy-safe (nessun
 * identificativo di sessione/visitatore persistito).
 */
class SecondReadAnalyticsController extends Controller
{
    public function __construct(
        private readonly ContinuationAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        $range = $this->resolveRange($request);

        $breakdown = $this->analytics->articleBreakdown($range['since'], $range['until']);
        $totals = $this->analytics->siteWideTotals($range['since'], $range['until']);

        return view('admin.second-read-analytics.index', [
            'breakdown' => $breakdown,
            'totals' => $totals,
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
            default => ['option' => 'sempre', 'since' => null, 'until' => null],
        };
    }
}
