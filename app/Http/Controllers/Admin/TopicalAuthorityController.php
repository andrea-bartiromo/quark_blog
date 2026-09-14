<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OrganicDiscovery\TopicalAuthorityGapService;
use Illuminate\View\View;

/**
 * Cantiere 8 (programma "Kairus Organic Discovery"): strategia editoriale
 * per cluster e autorevolezza — sola lettura, calcolata a ogni apertura
 * della pagina. Compone TopicalAuthorityGapService, mai un nuovo audit
 * strutturale o una nuova regola di prontezza.
 */
class TopicalAuthorityController extends Controller
{
    public function __construct(
        private readonly TopicalAuthorityGapService $gaps,
    ) {}

    public function index(): View
    {
        return view('admin.topical-authority.index', [
            'clusters' => $this->gaps->auditClusters(),
            'concepts' => $this->gaps->auditConcepts(),
            'stateLabels' => TopicalAuthorityGapService::stateLabels(),
        ]);
    }
}
