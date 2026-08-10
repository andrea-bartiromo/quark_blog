<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Riferimenti statici protetti
    |--------------------------------------------------------------------------
    |
    | disk_name codificati direttamente in controller, viste Blade o seeder
    | versionati nel repository. Non vengono scansionati a runtime: questo
    | elenco va aggiornato manualmente ogni volta che si introduce un nuovo
    | riferimento hardcoded a un file della Libreria media, cosi che il
    | preflight di spostamento possa bloccarlo in modo esplicito.
    */
    'protected_disk_names' => [
        'placeholder-1.jpg',
        'placeholder-1.svg',
        'hero-placeholder.svg',
        'turing/portraits/alan-turing-portrait.png',

        // Default/fallback hardcoded in TuringPageController (mai in un
        // campo DB, quindi mai scoperti da MediaReferenceService — sono
        // gli unici valori realmente statici, distinti dagli eventuali
        // override "background_image"/"image" salvati in
        // special_pages.content, quelli si' gia coperti dalla scansione
        // JSON esistente). Trovati con una ricerca esplicita di
        // 'turing/' in app/, non per deduzione — vedi
        // docs/EDITORIAL_MEDIA_WEBP.md.
        'turing/hero/turing-hero.webp',
        'turing/hero/turing-intro.webp',
        'turing/backgrounds/turing-universal-machine-background.webp',
        'turing/backgrounds/turing-legacy-panel.webp',
        'turing/backgrounds/turing-test-background.webp',
        'turing/backgrounds/turing-ai-background.webp',
        'turing/enigma.webp',
        'turing/enigma/turing-enigma-background.webp',
        'turing/enigma/turing-enigma-panel.webp',
        'turing/universal-machine.webp',
        'turing/turing-test.webp',
        'turing/modern-ai.webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cartelle di classificazione automatica
    |--------------------------------------------------------------------------
    |
    | Destinazione proposta da MediaClassificationService per ciascun dominio
    | logico di utilizzo, quando un Media e riferito da un solo dominio in
    | modo inequivocabile. Le cartelle "article" e "category" coincidono
    | volutamente con quelle gia create da MediaFolderSeeder (rispettivamente
    | "Copertine" sotto Articoli, e la cartella Categorie di primo livello),
    | perche gia usate con lo stesso significato dai flussi di upload
    | esistenti. Le cartelle "ad", "user" e "special_page" non esistono
    | ancora: vengono create solo al momento dell'applicazione (--apply) e
    | solo se esiste davvero un media da classificare in quel dominio,
    | tramite MediaFolderService::upsertDefinition() (idempotente).
    */
    'classification_folders' => [
        'article' => [
            'name' => 'Copertine',
            'slug' => 'covers',
            'path' => 'articles/covers',
            'parent_path' => 'articles',
        ],
        'ad' => [
            'name' => 'Pubblicità',
            'slug' => 'ads',
            'path' => 'ads',
            'parent_path' => null,
        ],
        'category' => [
            'name' => 'Categorie',
            'slug' => 'categories',
            'path' => 'categories',
            'parent_path' => null,
        ],
        'user' => [
            'name' => 'Autori',
            'slug' => 'authors',
            'path' => 'authors',
            'parent_path' => null,
        ],
        'special_page' => [
            'name' => 'Pagine speciali',
            'slug' => 'special-pages',
            'path' => 'special-pages',
            'parent_path' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Radice pubblica secondaria (sincronizzazione media)
    |--------------------------------------------------------------------------
    |
    | Su alcuni hosting (es. cPanel) la document root realmente servita dal
    | dominio (tipicamente public_html) e' una directory fisicamente separata
    | da public/assets/img dell'applicazione Laravel: scrivere solo li' non
    | rende il file davvero raggiungibile pubblicamente. Quando questo valore
    | e' impostato (env MEDIA_PUBLIC_ROOT), PublicMediaSyncService replica
    | ogni creazione, spostamento ed eliminazione di file nella Libreria
    | media anche in questa directory. Lasciare vuoto/nullo quando public/ e
    | la document root coincidono, o sono collegate da un symlink a livello
    | di sistema operativo: la sincronizzazione si disattiva da sola (vedi
    | PublicMediaSyncService::resolveTarget()).
    */
    'public_root' => env('MEDIA_PUBLIC_ROOT'),

    /*
    |--------------------------------------------------------------------------
    | Conversione WebP
    |--------------------------------------------------------------------------
    |
    | Parametri unici, condivisi da `media:webp-audit` (per le stime) e da
    | `media:convert-webp` (per la conversione reale): stessa policy per
    | entrambi, cosi' che l'audit sia un'anteprima fedele di cosa farebbe
    | davvero la conversione, non solo una stima approssimata.
    */
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),
    'webp_max_width' => (int) env('MEDIA_WEBP_MAX_WIDTH', 1600),

    /*
    |--------------------------------------------------------------------------
    | Conversione automatica in WebP per i nuovi upload
    |--------------------------------------------------------------------------
    |
    | Quando true (default), un nuovo upload JPG/PNG nella Libreria media, in
    | copertina articolo (Admin e Redazione) o in immagine categoria viene
    | convertito automaticamente in WebP prima di essere salvato — cosi' i
    | nuovi upload smettono di crescere lo storage in formati piu' pesanti.
    | Non riguarda mai i file gia' esistenti (quella e' la migrazione legacy
    | separata, deliberatamente distinta) ne' i flussi esplicitamente
    | esclusi (Turing: nessun record Media, alcuni riferimenti hardcoded).
    |
    | Interruttore di sicurezza reversibile senza deploy: impostare
    | MEDIA_AUTO_WEBP_ON_UPLOAD=false in produzione disattiva istantaneamente
    | la conversione per i nuovi upload (che tornano al comportamento
    | preesistente: stesso formato del sorgente), senza toccare codice.
    */
    'auto_webp_on_upload' => env('MEDIA_AUTO_WEBP_ON_UPLOAD', true),

];
