<?php

namespace App\Services\Communication;

use App\Contracts\EmailDeliveryProvider;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use Throwable;

/**
 * Orchestrazione di invio — SOLO con provider fake (NullEmailProvider /
 * RecordingEmailProvider). Nessun percorso reale di invio esiste in
 * questo repository: questa classe è la macchina che il futuro
 * incremento "test send reale" riuserà, collegando finalmente un vero
 * EmailDeliveryProvider al posto del fake.
 *
 * campaign ready -> claim send (queued->sending atomico) -> revalidate
 * -> render -> provider -> persist esito -> [retry se transient].
 *
 * Fasi di un singolo processSend() e cosa succede se ciascuna lancia:
 *
 *   1. Claim (queued->sending): se fallisce (già reclamato da un altro
 *      worker, o non più 'queued'), skip pulito, nessuna scrittura.
 *   2. Revalidazione (subscriber/campagna/sender): nessuna ambiguità
 *      possibile qui — un fallimento risolve SEMPRE a 'failed'
 *      immediato, il provider non è mai stato chiamato.
 *   3. Rendering: stessa garanzia — un'eccezione qui risolve a 'failed'
 *      immediato (render_exception), mai un retry automatico (un
 *      rendering deterministico che fallisce fallirà identico al
 *      prossimo tentativo, finché la campagna non viene corretta).
 *   4. Chiamata al provider: da qui in poi un'eccezione NON viene mai
 *      catturata — la riga resta 'sending' deliberatamente. È la stessa
 *      onestà già documentata in CommunicationDelivery: un crash tra la
 *      chiamata al provider e la persistenza dell'esito è un'ambiguità
 *      reale (il provider potrebbe aver "accettato" prima del crash),
 *      mai risolta automaticamente da questo layer — vedi
 *      StaleSendRecoveryService per la revisione manuale.
 *
 * MATRICE DEI FALLIMENTI (N2.10) — fonte canonica, riusata dal report
 * finale della missione. Ogni riga è coperta da almeno un test in
 * CampaignDeliveryOrchestratorTest (nome tra parentesi).
 *
 * | # | Scenario                                    | Rilevato in     | Esito riga comm_sends      | Ritentabile?              |
 * |---|----------------------------------------------|-----------------|------------------------------|----------------------------|
 * | 1 | Riga già 'sending'/terminale (non più queued) | Claim           | invariato (skip)              | n/a — non un fallimento    |
 * | 2 | Claim perso per corsa reale                   | Claim           | invariato (skip)              | n/a — un altro worker vince|
 * | 3 | Iscritto non più confermato/eliminato         | Revalidazione   | failed (subscriber_not_eligible) | mai                    |
 * | 4 | Campagna non più 'sending'/eliminata          | Revalidazione   | failed (campaign_not_sendable)   | mai                    |
 * | 5 | Mittente assente o archiviato                 | Revalidazione   | failed (sender_invalid)          | mai                    |
 * | 6 | Eccezione di rendering (contenuto non valido) | Rendering       | failed (render_exception)        | mai (deterministico)   |
 * | 7 | Provider: accepted                            | Chiamata provider | sent                        | n/a — successo             |
 * | 8 | Provider: transient_failure, attempts<max     | Chiamata provider | queued (retry, attempts+1)  | sì, entro DEFAULT_MAX_ATTEMPTS |
 * | 9 | Provider: transient_failure, attempts>=max    | Chiamata provider | failed (max_attempts_exceeded) | mai oltre il limite    |
 * |10 | Provider: rejected / permanent_failure        | Chiamata provider | failed (motivo del provider)   | mai                    |
 * |11 | Provider: eccezione PHP (crash/timeout reale) | Chiamata provider | 'sending' invariato — MAI auto-risolto, propaga | solo revisione manuale (StaleSendRecoveryService) |
 * |12 | Campagna annullata mentre la riga era queued  | cancelCampaign  | cancelled (bulk)               | mai                    |
 * |13 | Campagna annullata mentre la riga era già sending | Revalidazione (run successivo) | failed (campaign_not_sendable) | mai — il claim in corso si conclude da solo |
 * |14 | runCampaign() su campagna non transizionabile a sending | canTransition() pre-check | nessuna scrittura, ritorna null | n/a |
 */
class CampaignDeliveryOrchestrator
{
    public const DEFAULT_MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly CampaignRenderer $renderer,
        private readonly SendStateMachine $sendMachine,
        private readonly CampaignStateMachine $campaignMachine,
    ) {}

    /**
     * Elabora l'intera coda 'queued' di una campagna con il provider
     * dato. La campagna deve già essere in stato 'sending' (transizione
     * a carico del chiamante — runCampaign() lo fa per l'invio reale;
     * il dry-run lo fa dentro una transazione sempre annullata).
     */
    public function processQueue(CommunicationCampaign $campaign, EmailDeliveryProvider $provider, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): CampaignRunReport
    {
        $sendIds = CommunicationSend::where('campaign_id', $campaign->id)
            ->where('status', CommunicationSend::STATUS_QUEUED)
            ->pluck('id');

        $report = (new CampaignRunReport)->withEligible($sendIds->count());

        foreach ($sendIds as $sendId) {
            $send = CommunicationSend::find($sendId);

            if (! $send) {
                continue;
            }

            $outcome = $this->processSend($send, $provider, $maxAttempts);
            $report = $report->withOutcome($outcome);
        }

        return $report;
    }

    /**
     * Transiziona la campagna a 'sending', elabora la coda, poi
     * transiziona a 'completed'. Se la transizione iniziale fallisce
     * (già in un altro stato, corsa persa), non elabora nulla.
     */
    public function runCampaign(CommunicationCampaign $campaign, EmailDeliveryProvider $provider, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): ?CampaignRunReport
    {
        // canTransition() prima di transition(): quest'ultima lancia per
        // una transizione genuinamente non ammessa (es. una campagna già
        // terminale) — un errore di programmazione secondo la sua stessa
        // semantica, ma qui è un esito operativo del tutto normale (un
        // secondo run su una campagna già completata), mai un crash.
        if (! $this->campaignMachine->canTransition($campaign, CommunicationCampaign::STATUS_SENDING)) {
            return null;
        }

        if (! $this->campaignMachine->transition($campaign, CommunicationCampaign::STATUS_SENDING)) {
            return null;
        }

        $report = $this->processQueue($campaign, $provider, $maxAttempts);

        $this->campaignMachine->transition($campaign->fresh(), CommunicationCampaign::STATUS_COMPLETED);

        return $report;
    }

    /**
     * Cancellazione mid-flight (FASE 12): le righe ancora 'queued' (mai
     * reclamate) diventano 'cancelled' in blocco — un solo UPDATE
     * condizionato, sicuro sotto corsa con un worker che le reclama nello
     * stesso istante (chiunque arrivi prima vince, l'altro vede 0 righe
     * affette). Le righe già 'sending' NON vengono mai toccate qui: un
     * claim in corso si conclude nel proprio esito naturale — la
     * revalidazione dentro processSend() vedrà la campagna non più
     * 'sending' e le farà fallire con motivo esplicito, mai le
     * interrompe a metà.
     */
    public function cancelCampaign(CommunicationCampaign $campaign): bool
    {
        $transitioned = $this->campaignMachine->transition($campaign, CommunicationCampaign::STATUS_CANCELLED);

        if ($transitioned) {
            CommunicationSend::where('campaign_id', $campaign->id)
                ->where('status', CommunicationSend::STATUS_QUEUED)
                ->update(['status' => CommunicationSend::STATUS_CANCELLED]);
        }

        return $transitioned;
    }

    public function processSend(CommunicationSend $send, EmailDeliveryProvider $provider, int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): SendProcessingOutcome
    {
        // Stessa guardia di runCampaign(): processSend() deve restare
        // sicuro anche se chiamato su una riga che non è (più) 'queued'
        // (già reclamata, già terminale) — un esito normale ("qualcun
        // altro l'ha già presa", "è già stata processata"), mai
        // un'eccezione. Solo una vera corsa persa (stato in memoria
        // ancora 'queued', ma il DB ha già vinto altrove) passa dal
        // ramo transition()->false qui sotto.
        if (! $this->sendMachine->canTransition($send, CommunicationSend::STATUS_SENDING)) {
            return SendProcessingOutcome::skipped('not_queued');
        }

        if (! $this->sendMachine->transition($send, CommunicationSend::STATUS_SENDING)) {
            return SendProcessingOutcome::skipped('claim_lost');
        }

        $revalidation = $this->revalidate($send);

        if ($revalidation['failureReason'] !== null) {
            $this->sendMachine->transition($send, CommunicationSend::STATUS_FAILED, [
                'failed_at' => now(),
                'failure_reason' => $revalidation['failureReason'],
            ]);

            return SendProcessingOutcome::failed($revalidation['failureReason']);
        }

        try {
            $message = $this->renderer->render($revalidation['campaign'], $revalidation['subscriber']);
        } catch (Throwable $e) {
            // Nessuna ambiguità: il provider non è mai stato chiamato.
            // Risoluzione immediata, mai un retry automatico di un
            // rendering deterministico che fallirebbe identico.
            $this->sendMachine->transition($send, CommunicationSend::STATUS_FAILED, [
                'failed_at' => now(),
                'failure_reason' => 'render_exception',
            ]);

            return SendProcessingOutcome::failed('render_exception');
        }

        // Da qui in poi nessun catch: un'eccezione lascia la riga
        // 'sending' deliberatamente (ambiguità reale sull'esito).
        $result = $provider->deliver($message);

        return $this->persistResult($send, $result, $maxAttempts);
    }

    /**
     * @return array{subscriber: ?CommunicationSubscriber, campaign: ?CommunicationCampaign, failureReason: ?string}
     */
    private function revalidate(CommunicationSend $send): array
    {
        // Riletti FRESCHI dal DB, mai fidati dello snapshot al momento
        // del "Prepara destinatari": una riga 'queued' non autorizza mai
        // da sola la consegna (FASE 11 della missione).
        $subscriber = CommunicationSubscriber::find($send->subscriber_id);
        $campaign = CommunicationCampaign::find($send->campaign_id);

        if (! $subscriber || ! $subscriber->isEligibleForDelivery()) {
            return ['subscriber' => null, 'campaign' => null, 'failureReason' => 'subscriber_not_eligible'];
        }

        if (! $campaign || $campaign->trashed() || $campaign->status !== CommunicationCampaign::STATUS_SENDING) {
            return ['subscriber' => null, 'campaign' => null, 'failureReason' => 'campaign_not_sendable'];
        }

        if (! $campaign->senderProfile || $campaign->senderProfile->status !== CommunicationSenderProfile::STATUS_ACTIVE) {
            return ['subscriber' => null, 'campaign' => null, 'failureReason' => 'sender_invalid'];
        }

        return ['subscriber' => $subscriber, 'campaign' => $campaign, 'failureReason' => null];
    }

    private function persistResult(CommunicationSend $send, DeliveryResult $result, int $maxAttempts): SendProcessingOutcome
    {
        if ($result->isAccepted()) {
            $this->sendMachine->transition($send, CommunicationSend::STATUS_SENT, [
                'sent_at' => now(),
                'provider_message_id' => $result->providerMessageId,
                'attempts' => $send->attempts + 1,
            ]);

            return SendProcessingOutcome::sent();
        }

        $attempts = $send->attempts + 1;

        if ($result->isTransientFailure() && $attempts < $maxAttempts) {
            $this->sendMachine->transition($send, CommunicationSend::STATUS_QUEUED, [
                'attempts' => $attempts,
                'failure_reason' => $result->reason,
            ]);

            return SendProcessingOutcome::retried($result->reason ?? 'transient_failure');
        }

        $reason = $result->isTransientFailure() ? 'max_attempts_exceeded' : ($result->reason ?? 'permanent_failure');

        $this->sendMachine->transition($send, CommunicationSend::STATUS_FAILED, [
            'attempts' => $attempts,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ]);

        return SendProcessingOutcome::failed($reason);
    }
}
