<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PublicPages\PublicHealthDashboardService;
use Illuminate\View\View;

/**
 * Cantiere 30 (programma 100-cantieri Kairus) — dashboard admin "Salute
 * pubblica": un'unica pagina read-only che riassume gli audit tecnici
 * delle pagine pubbliche già introdotti nei Cantieri 22-29 (SEO/canonical,
 * redirect, 404 reali, collegamenti, media, WCAG), finora raggiungibili
 * solo da riga di comando. Nessuna azione di correzione automatica: solo
 * un riepilogo con rimando al comando Artisan corrispondente per il
 * dettaglio completo.
 */
class PublicHealthDashboardController extends Controller
{
    public function __construct(private readonly PublicHealthDashboardService $dashboard) {}

    public function index(): View
    {
        return view('admin.public-health-dashboard', [
            'snapshot' => $this->dashboard->snapshot(),
        ]);
    }
}
