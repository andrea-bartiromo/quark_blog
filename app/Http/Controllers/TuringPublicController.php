<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TuringPublicController extends Controller
{
    public function macchinaUniversale(): View
    {
        return view('turing.longform.macchina-universale');
    }

    public function crittografia(): View
    {
        return view('turing.longform.crittografia');
    }

    public function intelligenzaArtificiale(): View
    {
        return view('turing.longform.intelligenza-artificiale');
    }
}
