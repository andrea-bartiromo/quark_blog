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
    | Registro rilasci (Release Registry)
    |--------------------------------------------------------------------------
    |
    | REVISION e DEPLOY_INFO (scritti da deploy.sh) vivono dentro la
    | directory di release stessa: con lo schema a directory separate +
    | switch di symlink già in uso in produzione, vengono scartati al
    | rilascio successivo — nessuna storia sopravvive tra un deploy e
    | l'altro. Il "Principio di stato" della roadmap operativa
    | (costruito ≠ CI green ≠ merged ≠ deployed ≠ verified ≠ measured,
    | vedi docs/KAIRUS_TECHNICAL_ROADMAP_V14.md) oggi viene tracciato a
    | mano in una tabella Markdown, senza alcuna fonte automatica.
    |
    | Un percorso QUI configurato, FUORI dalla directory di release (es.
    | un file sibling alle directory di release stesse, mai dentro
    | ~/kairus_app), riceve un log append-only (JSON Lines) di ogni
    | evento registrato per revisione — deployed da deploy.sh, verified/
    | measured da comandi separati eseguiti in seguito. Lasciare questa
    | variabile non impostata disattiva completamente il registro
    | (App\Services\Deploy\ReleaseRegistry): nessun comportamento
    | esistente cambia finché non viene impostato esplicitamente
    | DEPLOY_RELEASE_REGISTRY_PATH — stesso principio già in uso per
    | served_public_root sopra e per MEDIA_PUBLIC_ROOT (config/media.php).
    */
    'release_registry_path' => env('DEPLOY_RELEASE_REGISTRY_PATH'),

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
