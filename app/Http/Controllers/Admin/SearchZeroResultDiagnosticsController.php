<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchZeroResultDiagnosticsService;
use Illuminate\View\View;

/**
 * Mission 31 — Search Zero-Result Diagnostics: superficie admin di sola
 * lettura sul segnale già raccolto da SearchZeroResultDiagnosticsService.
 * Nessuna nuova raccolta dati qui, stesso principio di
 * SecondReadAnalyticsController per ContinuationAnalyticsService.
 */
class SearchZeroResultDiagnosticsController extends Controller
{
    public function __construct(
        private readonly SearchZeroResultDiagnosticsService $diagnostics,
    ) {}

    public function index(): View
    {
        return view('admin.search-zero-result-diagnostics.index', [
            'queries' => $this->diagnostics->topUnresolved(),
        ]);
    }
}
