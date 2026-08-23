<?php

return [
    'name' => 'Kairus',
    'name_full' => 'Kairus — Divulgazione scientifica',
    'tagline' => 'La scienza spiegata come si deve',
    'description' => 'Kairus è il blog di divulgazione scientifica che spiega fisica, biologia, tecnologia e spazio in modo semplice, curioso e senza filtri.',

    // Fallback globale per og:image/twitter:image sulle pagine che non
    // definiscono un'immagine social propria (vedi layouts/partials/head).
    // PNG raster, non SVG: Facebook/Twitter/LinkedIn non renderizzano in modo
    // affidabile un SVG come immagine di condivisione.
    'default_share_image' => 'assets/icons/icon-512.png',

    'categories' => [
        'intelligenza-artificiale' => 'Intelligenza Artificiale',
        'energia' => 'Energia & Clima',
        'salute' => 'Salute & Biotech',
        'societa' => 'Tecnologia & Società',
        'spazio' => 'Spazio',
        'fisica' => 'Fisica',
        'ambiente' => 'Ambiente',
    ],

    // Vuoto finché i profili non esistono davvero: il footer nasconde il blocco
    // social quando questo array non contiene nulla.
    'social' => [],
];
