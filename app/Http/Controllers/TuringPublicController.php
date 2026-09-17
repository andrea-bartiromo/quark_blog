<?php

namespace App\Http\Controllers;

use App\Services\Turing\TuringNavigationMetricsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TuringPublicController extends Controller
{
    public function __construct(
        private readonly TuringNavigationMetricsService $navigationMetrics,
    ) {}

    public function enigma(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->navigationMetrics->recordView('enigma');

        return view('turing.enigma');
    }

    public function ai(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->navigationMetrics->recordView('ai');

        return view('turing.ai');
    }

    public function legacy(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->navigationMetrics->recordView('legacy');

        return view('turing.legacy');
    }

    public function computation(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->navigationMetrics->recordView('computation');

        return view('turing.computation');
    }

    public function intelligence(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        $this->navigationMetrics->recordView('intelligence');

        return view('turing.intelligence');
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
