<?php

return [

    // Usata quando un import non specifica la property (sito verificato in
    // Search Console): nessuna chiamata API esiste ancora in questo
    // programma (Cantiere 1 "Kairus Organic Discovery" resta CSV manuale),
    // quindi questo e' solo un'etichetta per distinguere piu' property in
    // futuro, mai un valore verificato contro l'API reale.
    'default_property' => env('SEARCH_CONSOLE_DEFAULT_PROPERTY'),

    // Termini usati per riconoscere le query "brand" (contengono il nome
    // del sito) cosi' da poterle escludere dai report di query non-brand.
    // Deterministico e configurabile: nessuna euristica linguistica, solo
    // una lista di sottostringhe case-insensitive.
    'brand_terms' => array_values(array_filter([
        config('app.name') ? mb_strtolower((string) config('app.name')) : null,
    ])),

];
