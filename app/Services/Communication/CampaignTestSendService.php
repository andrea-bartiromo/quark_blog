<?php

namespace App\Services\Communication;

use App\Contracts\EmailDeliveryProvider;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignActivityLog;
use App\Models\CommunicationSubscriber;
use App\Models\CommunicationTestSend;
use RuntimeException;
use Throwable;

/**
 * Provider Abstraction + Safe Test Send. Invia UN messaggio reale (o
 * fake nei test — mai deciso da questa classe, sempre dal chiamante
 * tramite $provider) a UN iscritto confermato esplicitamente scelto
 * dall'admin — mai alla lista destinatari (comm_sends) come farebbe
 * l'orchestrazione bulk.
 *
 * Contratto del "test send" (distinto per costruzione dall'invio bulk):
 *   - Richiede una campagna CONGELATA (CampaignFreezeService) — il
 *     contenuto testato è quello che resterà bloccato, non uno che può
 *     ancora cambiare sotto al test stesso.
 *   - Richiede communication.real_send_enabled=true — vedi
 *     config/communication.php. Un test-send con questo flag a false è
 *     un errore di programmazione del chiamante (l'admin controller
 *     verifica canTestSend() prima e non arriva mai qui in quel caso).
 *   - NON tocca mai comm_sends, CampaignStateMachine o SendStateMachine:
 *     è strutturalmente impossibile che questa classe segni una
 *     campagna come "inviata" — non referenzia mai comm_campaigns.status.
 *   - Registra l'esito in comm_test_sends (traccia separata, mai
 *     mescolata a comm_sends) e una singola riga in
 *     comm_campaign_activity_logs (visibilità nella Cronologia
 *     campagna) — mai l'indirizzo email del destinatario in nessuno dei
 *     due, solo l'id dell'iscritto tramite relazione.
 *   - Riusa CampaignRenderer, la STESSA identica pipeline di rendering
 *     di anteprima/dry-run/invio bulk: nessuna seconda implementazione
 *     che potrebbe divergere da cosa "sarebbe stato inviato".
 *   - Un'eccezione imprevista dal provider (non un DeliveryResult, un
 *     vero errore di programmazione/trasporto) viene comunque
 *     registrata come esito 'exception' — mai propagata a una pagina di
 *     errore generica, così l'admin vede sempre un esito nella
 *     Cronologia invece di un 500.
 */
class CampaignTestSendService
{
    public function __construct(
        private readonly CampaignPreflightService $preflight,
        private readonly CampaignRenderer $renderer,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('communication.real_send_enabled', false);
    }

    /**
     * @return list<string>
     */
    public function blockingReasons(CommunicationCampaign $campaign): array
    {
        $reasons = [];

        if (! $this->isEnabled()) {
            $reasons[] = 'Invio di test disabilitato (communication.real_send_enabled=false).';
        }

        if (! $campaign->isFrozen()) {
            $reasons[] = 'La campagna deve essere congelata prima di un invio di test.';
        }

        if (! $this->preflight->assess($campaign)->isReady()) {
            $reasons[] = 'La campagna non supera la verifica pre-invio.';
        }

        return $reasons;
    }

    public function canTestSend(CommunicationCampaign $campaign): bool
    {
        return $this->blockingReasons($campaign) === [];
    }

    public function send(
        CommunicationCampaign $campaign,
        CommunicationSubscriber $subscriber,
        EmailDeliveryProvider $provider,
        ?int $actorId,
    ): CommunicationTestSend {
        if (! $this->canTestSend($campaign)) {
            throw new RuntimeException(
                "Impossibile eseguire un invio di test per la campagna #{$campaign->id}: ".
                implode(' ', $this->blockingReasons($campaign))
            );
        }

        if (! $subscriber->isEligibleForDelivery()) {
            throw new RuntimeException(
                "L'iscritto scelto non è (più) un iscritto confermato: invio di test rifiutato."
            );
        }

        $rendered = $this->renderer->render($campaign, $subscriber);

        try {
            $result = $provider->deliver($rendered);
            $status = $result->status;
            $providerMessageId = $result->providerMessageId;
            $failureReason = $result->reason;
        } catch (Throwable) {
            $status = CommunicationTestSend::STATUS_EXCEPTION;
            $providerMessageId = null;
            $failureReason = 'unexpected_exception';
        }

        $testSend = CommunicationTestSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'sender_profile_id' => $campaign->sender_profile_id,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'failure_reason' => $failureReason,
            'triggered_by' => $actorId,
            'created_at' => now(),
        ]);

        CommunicationCampaignActivityLog::record(
            campaign: $campaign,
            subjectType: 'test_send',
            subjectId: $testSend->id,
            subjectTitle: $campaign->title,
            action: "Invio di test eseguito (esito: {$status})",
            userId: $actorId,
        );

        return $testSend;
    }
}
