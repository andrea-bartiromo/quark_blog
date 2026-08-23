<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invio di test — abilitazione
    |--------------------------------------------------------------------------
    |
    | Il "Sistema Comunicazione" (comm_*) non ha mai inviato un'email reale
    | fino a questo incremento (Provider Abstraction + Safe Test Send).
    | MailerEmailProvider — l'unica implementazione REALE di
    | App\Contracts\EmailDeliveryProvider in questo repository — riusa il
    | mailer già configurato in config/mail.php/.env (lo stesso transport
    | della Newsletter legacy, nessuna nuova credenziale/SDK), ma resta
    | inerte finché questo flag non viene abilitato deliberatamente.
    |
    | Il flag NON influenza in alcun modo l'invio bulk reale (che non
    | esiste ancora — CampaignDeliveryOrchestrator continua a operare
    | solo con NullEmailProvider/RecordingEmailProvider ovunque venga
    | chiamato in questo codebase). Governa solo l'azione admin "Invio
    | di test": un singolo destinatario esplicitamente scelto, mai la
    | lista destinatari, vedi CampaignTestSendService.
    |
    | Default: false. Da abilitare solo dopo una decisione operativa
    | deliberata (credenziali mittente reali verificate, dominio
    | autorizzato, ecc.) — mai come effetto collaterale di un deploy.
    |
    */

    'real_send_enabled' => env('COMMUNICATION_REAL_SEND_ENABLED', false),

];
