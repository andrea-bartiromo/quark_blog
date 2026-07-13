<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class TuringPublicController extends Controller
{
    public function enigma(): View
    {
        return view('turing.enigma');
    }

    public function ai(): View
    {
        return view('turing.ai');
    }
}
