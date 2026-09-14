<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Trust\TrustPilotGateReadinessService;
use Illuminate\View\View;

/**
 * Cantiere 42 (programma "100 cantieri Kairus"): mostra SOLO lo stato di
 * sola lettura delle tre condizioni del NO-GO B-45 per il pilot Trust
 * pubblico — mai una route pubblica, mai un form, mai una scrittura. Si
 * legga il docblock di TrustPilotGateReadinessService per il dettaglio
 * delle tre condizioni e la decisione di scope che ha limitato questo
 * cantiere alla sola lettura.
 */
class TrustPilotGateReadinessController extends Controller
{
    public function __construct(
        private readonly TrustPilotGateReadinessService $readiness,
    ) {}

    public function index(): View
    {
        return view('admin.trust-knowledge.gate-readiness', [
            'conditions' => $this->readiness->assess(),
            'allConditionsMet' => $this->readiness->allConditionsMet(),
        ]);
    }
}
