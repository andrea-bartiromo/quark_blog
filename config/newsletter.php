<?php

return [
    /*
    |--------------------------------------------------------------------
    | Interruttore di emergenza — invio newsletter settimanale
    |--------------------------------------------------------------------
    |
    | Prompt 116-120 (150-prompt program, readiness operativa): prima di
    | questa opzione, l'unico modo di fermare l'invio schedulato del
    | giovedì (routes/console.php, già in produzione) era commentare la
    | riga Schedule::command('newsletter:send') e fare un deploy — piu'
    | lento di quanto un vero incidente in corso richieda. Impostare
    | NEWSLETTER_SEND_ENABLED=false ferma sia l'invio schedulato sia il
    | pulsante "Invia ora" in /admin/newsletter (stesso comando
    | sottostante per entrambi) senza toccare codice. Default true:
    | nessun cambiamento al comportamento esistente finché non viene
    | esplicitamente disattivato.
    |
    */

    'send_enabled' => (bool) env('NEWSLETTER_SEND_ENABLED', true),

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
