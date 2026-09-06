<?php

return [
    /*
    |--------------------------------------------------------------------
    | Recupero prudente degli iscritti pendenti
    |--------------------------------------------------------------------
    |
    | Un iscritto "pendente" (confirmed=false) non riceve mai email
    | automaticamente riattivate: ogni invio di riconferma è un'azione
    | manuale di un editor da /admin/newsletter. Questi valori limitano
    | quanto insistentemente si può ri-sollecitare lo stesso indirizzo.
    |
    */

    'reconfirmation' => [
        // Un token di riconferma smette di essere valido dopo N giorni.
        'expires_after_days' => 7,

        // Numero massimo di email di riconferma inviabili allo stesso
        // iscritto pendente, a vita (non per finestra temporale).
        'max_attempts' => 3,

        // Ore minime tra un invio e il successivo per lo stesso iscritto,
        // indipendentemente dal limite sopra.
        'cooldown_hours' => 24,
    ],
];
