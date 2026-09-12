<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditFindingStatus;
use App\Services\PublicPages\AuditFindingStatusService;
use App\Services\PublicPages\PublicHealthDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cantiere 30 (programma 100-cantieri Kairus) — dashboard admin "Salute
 * pubblica": un'unica pagina che riassume gli audit tecnici delle pagine
 * pubbliche già introdotti nei Cantieri 22-29 (SEO/canonical, redirect,
 * 404 reali, collegamenti, media, WCAG), finora raggiungibili solo da
 * riga di comando. Nessuna azione di correzione automatica: solo un
 * riepilogo con rimando al comando Artisan corrispondente per il
 * dettaglio completo.
 *
 * Cantiere 31: aggiunge l'unica azione che questa pagina compie
 * (`updateFindingStatus`) — un workflow editoriale "presa in carico"/
 * "ignorato" per singolo finding, stesso pattern già in produzione per
 * SearchOpportunityController::updateStatus(). Nessuna correzione
 * automatica del finding stesso: solo uno stato assegnato a mano.
 */
class PublicHealthDashboardController extends Controller
{
    public function __construct(
        private readonly PublicHealthDashboardService $dashboard,
        private readonly AuditFindingStatusService $findingStatuses,
    ) {}

    public function index(): View
    {
        return view('admin.public-health-dashboard', [
            'snapshot' => $this->dashboard->snapshot(),
            'statusOptions' => AuditFindingStatus::statusOptions(),
        ]);
    }

    public function updateFindingStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'in:'.implode(',', PublicHealthDashboardService::REAL_DOMAIN_KEYS)],
            'finding_key' => ['required', 'string', 'max:600'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(AuditFindingStatus::statusOptions()))],
        ]);

        $this->findingStatuses->setStatus(
            $validated['domain'],
            $validated['finding_key'],
            $validated['status'],
            $request->user(),
        );

        return back()->with('status', 'Stato aggiornato.');
    }
}
