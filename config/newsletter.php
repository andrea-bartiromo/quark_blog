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

        // Interruttore di emergenza per la SOLA pulizia automatica
        // schedulata (newsletter:reconfirmation-cleanup, giornaliera
        // 04:30 — routes/console.php). Analogo a NEWSLETTER_SEND_ENABLED
        // per newsletter:send: disattivandolo, il comando esce senza
        // cancellare nulla, ma resta true di default (nessun comportamento
        // esistente cambia finché non viene impostato esplicitamente a
        // false). Non copre l'azione manuale equivalente dell'editor da
        // /admin/newsletter (NewsletterController::cleanupExpiredPending):
        // quella resta sempre disponibile perché è già una decisione umana
        // deliberata, non un'attivazione automatica non presidiata.
        'cleanup_enabled' => (bool) env('NEWSLETTER_RECONFIRMATION_CLEANUP_ENABLED', true),
    ],
];
