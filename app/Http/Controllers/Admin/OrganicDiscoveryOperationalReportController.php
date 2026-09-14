<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrganicDiscovery\OrganicDiscoveryOperationalReportService;
use Illuminate\View\View;

/**
 * Cantiere 7 (programma "Kairus Organic Discovery"): report operativo —
 * un unico punto di lettura periodico sullo stato del programma, sola
 * lettura, calcolato a ogni apertura della pagina. Compone esclusivamente
 * i servizi già esistenti dei Cantieri 1/3/4/5 (vedi
 * OrganicDiscoveryOperationalReportService), mai una nuova regola.
 */
class OrganicDiscoveryOperationalReportController extends Controller
{
    public function __construct(
        private readonly OrganicDiscoveryOperationalReportService $report,
    ) {}

    public function index(): View
    {
        return view('admin.organic-discovery-operational-report.index', [
            'snapshot' => $this->report->snapshot(),
        ]);
    }
}
