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

];
