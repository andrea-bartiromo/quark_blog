<?php

namespace App\Contracts;

use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RenderedCampaignMessage;

/**
 * Contratto definitivo del futuro provider email reale (FASE 7 della
 * missione Newsletter 2.0). Implementato SOLO da NullEmailProvider e
 * RecordingEmailProvider in questo repository — nessun SMTP/API
 * provider reale (SES, Mailgun, Brevo, Resend, Postmark o simili) è
 * introdotto qui, per vincolo esplicito della missione.
 *
 * Un'implementazione reale dovrà:
 *   - non lanciare mai per un rifiuto/fallimento del provider: quello è
 *     un DeliveryResult (status), non un'eccezione — un'eccezione resta
 *     riservata a un errore di programmazione o di trasporto irrecuperabile;
 *   - non includere mai credenziali/segreti nel messaggio d'errore di
 *     un'eventuale eccezione;
 *   - restituire un provider_message_id quando il provider reale ne
 *     espone uno, per la correlazione con webhook futuri (vedi
 *     DeliveryEventIngestionService);
 *   - restituire metadata non sensibili (mai corpo email, mai token,
 *     mai PII oltre a quanto già presente nel RenderedCampaignMessage).
 */
interface EmailDeliveryProvider
{
    public function deliver(RenderedCampaignMessage $message): DeliveryResult;
}
