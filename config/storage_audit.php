<?php

/**
 * Kairus — Budget di storage per l'hosting corrente.
 *
 * Puramente informativo: nessun comportamento applicativo dipende da
 * questo valore. Usato solo da `storage:audit` per calcolare una
 * percentuale di utilizzo indicativa rispetto allo spazio disco del
 * piano di hosting in uso, che Laravel non può altrimenti conoscere.
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Budget di storage (byte)
    |--------------------------------------------------------------------------
    |
    | Spazio disco totale assegnato dal piano di hosting corrente. Configura
    | STORAGE_BUDGET_BYTES per un valore esatto, oppure STORAGE_BUDGET_GB per
    | un valore approssimato in GB (1 GB = 1024^3 byte). Se nessuno dei due è
    | impostato, il default riflette il piano di hosting attuale di Kairus
    | (5 GB) — ma questo valore va sempre riverificato lato pannello di
    | hosting: il repository non può sapere quale piano è realmente attivo.
    |
    */

    'budget_bytes' => (int) env(
        'STORAGE_BUDGET_BYTES',
        (int) env('STORAGE_BUDGET_GB', 5) * 1024 * 1024 * 1024
    ),

];
