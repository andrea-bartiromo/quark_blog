<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4 — Measurement ID
    |--------------------------------------------------------------------------
    |
    | Il Measurement ID GA4 non e' un segreto (e' sempre visibile nel sorgente
    | HTML di qualunque pagina che lo usa), quindi il default qui sotto e'
    | volutamente il valore reale gia' in uso in produzione prima di questa
    | missione — cosi' un ambiente che non imposta esplicitamente
    | GA4_MEASUREMENT_ID in .env continua a comportarsi esattamente come
    | prima. Impostare la variabile in .env resta comunque il modo corretto
    | per cambiarlo, o per svuotarlo esplicitamente e disattivare Analytics
    | del tutto (vedi 'enabled' sotto: un Measurement ID vuoto disattiva il
    | caricamento indipendentemente da ogni altra impostazione).
    */
    'measurement_id' => env('GA4_MEASUREMENT_ID', 'G-Y1853N6FZP'),

    /*
    |--------------------------------------------------------------------------
    | Interruttore per ambiente
    |--------------------------------------------------------------------------
    |
    | null (default) = comportamento automatico: Analytics si carica SOLO in
    | produzione (config('app.env') === 'production'). Locale, testing ed
    | eventuale staging non taggato "production" non caricano mai GA4, senza
    | bisogno di alcuna azione — cosi' lavorare in locale o eseguire la
    | suite PHPUnit non puo' mai contaminare accidentalmente la proprieta'
    | GA4 reale.
    |
    | true/false = override esplicito che vince sempre sul rilevamento
    | automatico, per i casi in cui serva davvero (es. verificare GA4 una
    | tantum su uno staging pubblico, o disattivarlo temporaneamente anche
    | in produzione senza deploy).
    */
    'enabled' => env('ANALYTICS_ENABLED'),

    /*
    |--------------------------------------------------------------------------
    | Esclusione traffico interno — cookie first-party
    |--------------------------------------------------------------------------
    |
    | Durata in giorni del cookie che marca un browser come "traffico interno":
    | quando presente (impostato dall'amministratore autenticato tramite le
    | rotte admin.analytics.exclude/reactivate), AnalyticsExclusionService
    | impedisce del tutto il caricamento dello script GA4 su quel browser,
    | indipendentemente da tutto il resto. Durata lunga di default (2 anni,
    | stesso ordine di grandezza del componente ufficiale Google Analytics
    | Opt-out): e' un'esclusione volontaria e reversibile in ogni momento
    | dalla pagina profilo admin, non una sessione da far scadere in fretta.
    */
    'exclusion_cookie_days' => (int) env('ANALYTICS_EXCLUSION_COOKIE_DAYS', 730),

];
