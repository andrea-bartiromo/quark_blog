<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsExclusionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Analytics Hygiene: attiva/disattiva l'esclusione di QUESTO browser da
 * Google Analytics 4. Sotto lo stesso gruppo di rotte ['auth','editor']
 * di tutto il resto dell'area admin (routes/web.php) — nessuna nuova
 * autorizzazione da definire, stessa popolazione che ha gia' accesso al
 * pannello.
 *
 * Entrambe le azioni operano SOLO sul cookie del browser che effettua la
 * richiesta: nessuna scrittura sul database, nessun collegamento
 * all'utente autenticato oltre al controllo di autorizzazione stesso —
 * il cookie non contiene alcun identificativo, vedi
 * AnalyticsExclusionService.
 */
class AnalyticsExclusionController extends Controller
{
    public function __construct(
        private readonly AnalyticsExclusionService $exclusion
    ) {}

    public function exclude(Request $request): RedirectResponse
    {
        return back()
            ->with('success', 'Analytics escluso su questo browser: le tue visite non verranno piu\' conteggiate.')
            ->withCookie($this->exclusion->exclude($request));
    }

    public function reactivate(): RedirectResponse
    {
        return back()
            ->with('success', 'Analytics riattivato su questo browser.')
            ->withCookie($this->exclusion->reactivate());
    }
}
