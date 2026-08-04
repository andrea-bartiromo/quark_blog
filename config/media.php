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

];
