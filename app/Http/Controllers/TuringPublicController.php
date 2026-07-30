<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TuringPublicController extends Controller
{
    public function enigma(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        return view('turing.enigma');
    }

    public function ai(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        return view('turing.ai');
    }

    public function legacy(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        return view('turing.legacy');
    }

    public function computation(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

        return view('turing.computation');
    }

    public function intelligence(): View|RedirectResponse
    {
        if (! $this->chaptersArePublic()) {
            return $this->redirectToTuring();
        }

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
