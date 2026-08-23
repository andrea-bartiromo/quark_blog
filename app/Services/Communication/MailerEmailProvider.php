<?php

namespace App\Services\Communication;

use App\Contracts\EmailDeliveryProvider;
use App\Mail\CommunicationTestSendMailable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Provider Abstraction + Safe Test Send — unica implementazione REALE di
 * EmailDeliveryProvider in questo repository. Riusa il mailer già
 * configurato in config/mail.php/.env (lo stesso transport della
 * Newsletter legacy): nessuna nuova credenziale, nessun nuovo SDK ESP,
 * nessuna riga di database coinvolta — esattamente il pattern già
 * scelto per CommunicationSenderProfile::PROVIDER_SMTP (etichetta
 * soltanto, mai una credenziale in una riga).
 *
 * Deliberatamente MAI legato al container come binding di default: va
 * istanziato esplicitamente solo dal chiamante che ha già verificato
 * config('communication.real_send_enabled') — vedi
 * CampaignTestSendService::send(). CampaignDeliveryOrchestrator e
 * CampaignDryRunService continuano, invariati, a operare solo con
 * NullEmailProvider/RecordingEmailProvider ovunque vengano chiamati:
 * questa classe non introduce alcun invio bulk reale.
 *
 * Zero I/O nei test: Mail::fake() intercetta Mail::to()->send() PRIMA
 * di qualunque transport reale (verificato da
 * MailerEmailProviderTest::test_never_reaches_a_real_transport_under_mail_fake),
 * quindi questa classe stessa può essere collaudata in sicurezza senza
 * mai usare credenziali reali anche se presenti in .env.
 */
class MailerEmailProvider implements EmailDeliveryProvider
{
    public function deliver(RenderedCampaignMessage $message): DeliveryResult
    {
        if (blank($message->recipientEmail)) {
            return new DeliveryResult(
                status: DeliveryResult::STATUS_PERMANENT_FAILURE,
                idempotencyKey: $message->idempotencyKey,
                reason: 'missing_recipient_email',
            );
        }

        try {
            $sent = Mail::to($message->recipientEmail)->send(new CommunicationTestSendMailable($message));
        } catch (TransportExceptionInterface $e) {
            // Errore di trasporto (connessione/timeout/autenticazione col
            // server SMTP): non una decisione del provider sul messaggio
            // stesso, quindi trattato come transitorio — mai il messaggio
            // d'errore originale nel reason (potrebbe contenere host/
            // credenziali del transport).
            return new DeliveryResult(
                status: DeliveryResult::STATUS_TRANSIENT_FAILURE,
                idempotencyKey: $message->idempotencyKey,
                reason: 'transport_exception',
            );
        }

        // Sotto Mail::fake() (sempre il caso nei test automatici) $sent è
        // null: MailFake non costruisce un vero SentMessage. Un
        // provider_message_id assente resta un esito onesto, non un
        // errore — la stessa scelta già documentata per
        // CommunicationDeliveryService.
        $providerMessageId = $sent?->getSymfonySentMessage()?->getMessageId();

        return new DeliveryResult(
            status: DeliveryResult::STATUS_ACCEPTED,
            providerMessageId: $providerMessageId,
            idempotencyKey: $message->idempotencyKey,
        );
    }
}
