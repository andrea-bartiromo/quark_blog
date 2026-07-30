<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Turing chapters public visibility
    |--------------------------------------------------------------------------
    |
    | Quando false, /turing mostra una landing "In arrivo" invece dell'hub
    | completo, e i capitoli (/turing/enigma, /turing/ai, /turing/legacy,
    | /turing/computation, /turing/intelligence) reindirizzano con 302 a
    | /turing invece di mostrare i contenuti. Le viste e i controller dei
    | capitoli restano invariati e pronti: per il rilascio pubblico dello
    | Speciale basta impostare questo valore a true (o TURING_CHAPTERS_PUBLIC
    | =true nell'.env), senza altre modifiche al codice.
    |
    */

    'chapters_public' => env('TURING_CHAPTERS_PUBLIC', false),

];
