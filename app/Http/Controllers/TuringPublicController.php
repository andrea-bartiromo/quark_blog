<?php

namespace App\Http\Controllers;

use App\Services\Turing\TuringNavigationMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TuringPublicController extends Controller
{
    public function __construct(
        private readonly TuringNavigationMetricsService $navigationMetrics,
    ) {}

    public function enigma(Request $request): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->recordViewUnlessInternalAudit($request, 'enigma');

        return view('turing.enigma');
    }

    public function ai(Request $request): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->recordViewUnlessInternalAudit($request, 'ai');

        return view('turing.ai');
    }

    public function legacy(Request $request): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->recordViewUnlessInternalAudit($request, 'legacy');

        return view('turing.legacy');
    }

    public function computation(Request $request): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->recordViewUnlessInternalAudit($request, 'computation');

        return view('turing.computation');
    }

    public function intelligence(Request $request): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->recordViewUnlessInternalAudit($request, 'intelligence');

        return view('turing.intelligence');
    }

    /**
     * Codex (PR #623, P1): gli audit interni (SEO/WCAG, dashboard salute
     * pubblica + baseline mensile programmata) raggiungono ogni capitolo
     * Turing abilitato con un vero GET in-process (InProcessPageFetcher)
     * per verificarlo — senza questo controllo, ogni esecuzione
     * dell'audit gonfierebbe le metriche di navigazione reali con
     * traffico sintetico. Stesso marcatore esplicito già usato per non
     * contaminare le analytics reali degli articoli (vedi
     * ArticleController::show()).
     */
    private function recordViewUnlessInternalAudit(Request $request, string $chapter): void
    {
        if ($request->headers->has('X-Kairus-Internal-Audit')) {
            return;
        }

        $this->navigationMetrics->recordView($chapter);
    }

    private function chaptersArePublic(): bool
    {
        return (bool) config('turing.chapters_public');
    }

    /* Rilascio pubblico dello Speciale (vedi config/turing.php): finche' i
       capitoli non sono completi, le rotte /turing/* reindirizzano a
       /turing invece di mostrare i contenuti. Le viste restano invariate e
       pronte per quando i capitoli torneranno pubblici. */
    private function redirectToTuring(): RedirectResponse
    {
        return redirect()->route('turing', status: 302);
    }
}
