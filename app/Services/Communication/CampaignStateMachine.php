<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use RuntimeException;

/**
 * Tabella delle transizioni AMMESSE per CommunicationCampaign, esplicita e
 * testata — non nomi nuovi, gli stessi sei stati già definiti dal modello
 * (mai esistiti prima "preparing"/"ready": preparare i destinatari è
 * un'azione additiva che non cambia lo stato campagna, vedi
 * RecipientSnapshotService, non un passo della state machine).
 *
 *   draft      -> scheduled, sending, cancelled
 *   scheduled  -> draft, sending, cancelled
 *   sending    -> completed, failed, cancelled
 *   completed  -> (terminale)
 *   failed     -> (terminale)
 *   cancelled  -> (terminale)
 *
 * 'sending' è raggiungibile solo dall'orchestrazione di invio (mai da
 * un'azione admin diretta in questa missione — nessun pulsante "Invia"
 * esiste). Le transizioni terminali sono deliberatamente chiuse: non
 * esiste un concetto di "reinvio" della stessa campagna in questa
 * missione, quindi failed/cancelled non tornano indietro.
 */
class CampaignStateMachine
{
    private const TRANSITIONS = [
        CommunicationCampaign::STATUS_DRAFT => [
            CommunicationCampaign::STATUS_SCHEDULED,
            CommunicationCampaign::STATUS_SENDING,
            CommunicationCampaign::STATUS_CANCELLED,
        ],
        CommunicationCampaign::STATUS_SCHEDULED => [
            CommunicationCampaign::STATUS_DRAFT,
            CommunicationCampaign::STATUS_SENDING,
            CommunicationCampaign::STATUS_CANCELLED,
        ],
        CommunicationCampaign::STATUS_SENDING => [
            CommunicationCampaign::STATUS_COMPLETED,
            CommunicationCampaign::STATUS_FAILED,
            CommunicationCampaign::STATUS_CANCELLED,
        ],
        CommunicationCampaign::STATUS_COMPLETED => [],
        CommunicationCampaign::STATUS_FAILED => [],
        CommunicationCampaign::STATUS_CANCELLED => [],
    ];

    public function canTransition(CommunicationCampaign $campaign, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$campaign->status] ?? [], true);
    }

    /**
     * Esegue la transizione con una UPDATE atomica guardata dallo stato
     * IN MEMORIA di $campaign (non una semplice save()): due chiamate
     * concorrenti che partono dallo stesso stato letto non possono mai
     * applicare entrambe la propria transizione — la seconda vede 0 righe
     * affette e riceve false, non un'eccezione (non è un errore di
     * programmazione, è una corsa reale persa).
     *
     * @throws RuntimeException se la transizione non è ammessa dalla
     *                          tabella (errore di programmazione, non una corsa).
     */
    public function transition(CommunicationCampaign $campaign, string $to): bool
    {
        if (! $this->canTransition($campaign, $to)) {
            throw new RuntimeException(
                "Transizione campagna non ammessa: '{$campaign->status}' -> '{$to}'."
            );
        }

        $fromStatus = $campaign->status;

        $attributes = ['status' => $to];

        if ($to === CommunicationCampaign::STATUS_SENDING) {
            $attributes['sending_started_at'] = now();
        }

        if ($to === CommunicationCampaign::STATUS_COMPLETED) {
            $attributes['completed_at'] = now();
        }

        $affected = CommunicationCampaign::where('id', $campaign->id)
            ->where('status', $fromStatus)
            ->update($attributes);

        if ($affected === 1) {
            $campaign->forceFill($attributes);

            return true;
        }

        // Un altro processo ha già transizionato la campagna nel
        // frattempo: allinea l'istanza in memoria allo stato reale invece
        // di lasciarla mentire, cosicché il chiamante possa decidere cosa
        // fare con lo stato AGGIORNATO, non quello stantio.
        $campaign->refresh();

        return false;
    }
}
