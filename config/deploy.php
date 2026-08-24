<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Radice pubblica effettivamente servita (drift detector)
    |--------------------------------------------------------------------------
    |
    | Kairus in produzione ha due document root fisicamente separate:
    | l'albero applicativo (~/kairus_app/public, da cui public_path() legge
    | — e che questo config chiama "app root") e l'albero effettivamente
    | servito da Apache (tipicamente ~/public_html — "served root").
    | Nessun deploy automatico in questo repository copia i file statici
    | versionati (CSS/JS/icone) tra i due, quindi possono divergere in
    | contenuto: vedi docs/DEPLOYMENT.md per l'incidente reale che lo ha
    | dimostrato (public-premium.css, notte del 24/08).
    |
    | Lasciare questa variabile non impostata disattiva completamente il
    | drift detector (App\Services\Deploy\PublicAssetDriftDetector) e il
    | comando `deploy:asset-drift`: nessun comportamento esistente cambia
    | finche' non viene impostato esplicitamente DEPLOY_SERVED_PUBLIC_ROOT
    | — stesso principio già in uso per MEDIA_PUBLIC_ROOT
    | (config/media.php).
    */
    'served_public_root' => env('DEPLOY_SERVED_PUBLIC_ROOT'),

    /*
    |--------------------------------------------------------------------------
    | Percorsi da confrontare (drift detector)
    |--------------------------------------------------------------------------
    |
    | Solo i file statici gestiti dalla release Git e non già coperti da un
    | proprio meccanismo di sincronizzazione: CSS/JS versionati e i file
    | statici di primo livello (favicon, manifest, robots, icone). Esclude
    | deliberatamente:
    |
    |   - public/assets/img — Libreria media, già sincronizzata a runtime
    |     da PublicMediaSyncService (config/media.php);
    |   - public/images — grande albero editoriale curato manualmente
    |     (decine di MB), a basso rischio di questa classe di incidente
    |     (nessun cache-busting ?v= la riguarda) e volutamente fuori scope
    |     di default per non fare scanning indiscriminato di alberi ampi
    |     (vedi Mission 04).
    |
    | Una directory qui elencata viene scansionata ricorsivamente; un
    | singolo file viene confrontato direttamente. Estendibile in futuro
    | senza modificare il codice del servizio.
    */
    'asset_drift_scan_paths' => [
        'css',
        'js',
        'assets/icons',
        'favicon.ico',
        'apple-touch-icon.png',
        'site.webmanifest',
        'robots.txt',
    ],
];
