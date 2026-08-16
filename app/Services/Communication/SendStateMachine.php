<?php

namespace App\Services\Communication;

use App\Models\CommunicationSend;
use RuntimeException;

/**
 * Tabella delle transizioni AMMESSE per CommunicationSend (comm_sends),
 * a livello di singola riga destinatario/campagna.
 *
 *   queued   -> sending (claim), cancelled (campagna annullata prima di
 *               qualunque tentativo)
 *   sending  -> sent, failed, queued (retry dopo un transient failure)
 *   sent     -> (terminale)
 *   failed   -> (terminale — nessun retry automatico da qui: se attempts
 *               non ha ancora raggiunto il massimo, il fallimento resta
 *               'sending' -> 'queued', mai 'sending' -> 'failed' -> 'queued')
 *   cancelled -> (terminale)
 *
 * Deliberatamente 'sending' non transiziona MAI a 'cancelled': un claim
 * già in corso non viene mai interrotto da una cancellazione campagna
 * concorrente (vedi CampaignDeliveryOrchestrator) — lo si lascia
 * concludere nel proprio esito naturale, per non introdurre uno stato
 * ambiguo "stavo per finire ma qualcuno mi ha cancellato a metà".
 */
class SendStateMachine
{
    private const TRANSITIONS = [
        CommunicationSend::STATUS_QUEUED => [
            CommunicationSend::STATUS_SENDING,
            CommunicationSend::STATUS_CANCELLED,
        ],
        CommunicationSend::STATUS_SENDING => [
            CommunicationSend::STATUS_SENT,
            CommunicationSend::STATUS_FAILED,
            CommunicationSend::STATUS_QUEUED,
        ],
        CommunicationSend::STATUS_SENT => [],
        CommunicationSend::STATUS_FAILED => [],
        CommunicationSend::STATUS_CANCELLED => [],
        // 'delivered'/'bounced' sono stati di feedback provider (webhook,
        // vedi DeliveryEventIngestionService) mai raggiunti da una
        // transizione di questa macchina — restano fuori da questa
        // tabella per non implicare che l'orchestratore possa produrli.
    ];

    public function canTransition(CommunicationSend $send, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$send->status] ?? [], true);
    }

    /**
     * Come CampaignStateMachine::transition(): UPDATE atomica guardata
     * dallo stato in memoria, mai una save() incondizionata. Restituisce
     * false (non lancia) se un altro processo ha già vinto la corsa sulla
     * stessa riga — questa è la difesa reale contro il doppio invio, non
     * un caso d'errore.
     *
     * @throws RuntimeException se la transizione non è ammessa dalla
     *                          tabella (errore di programmazione, non una corsa).
     */
    public function transition(CommunicationSend $send, string $to, array $extra = []): bool
    {
        if (! $this->canTransition($send, $to)) {
            throw new RuntimeException(
                "Transizione send non ammessa: '{$send->status}' -> '{$to}'."
            );
        }

        $fromStatus = $send->status;
        $attributes = array_merge(['status' => $to], $extra);

        $affected = CommunicationSend::where('id', $send->id)
            ->where('status', $fromStatus)
            ->update($attributes);

        if ($affected === 1) {
            $send->forceFill($attributes);

            return true;
        }

        $send->refresh();

        return false;
    }
}
