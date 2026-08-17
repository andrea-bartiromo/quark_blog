<?php

namespace App\Services\Communication;

use App\Models\CommunicationSend;
use Illuminate\Database\Eloquent\Collection;

/**
 * Una riga comm_sends bloccata in 'sending' oltre una finestra
 * ragionevole rappresenta un'AMBIGUITÀ REALE (worker crashato tra la
 * chiamata al provider e la persistenza dell'esito — vedi il docblock
 * di CampaignDeliveryOrchestrator), mai un errore da correggere
 * automaticamente: un provider reale futuro potrebbe aver già accettato
 * il messaggio prima del crash, e riportarla ciecamente a 'queued'
 * rischierebbe un vero doppio invio quando questa pipeline sarà
 * collegata a un provider reale.
 *
 * Questo servizio quindi SOLO trova e riporta — non rilascia mai nulla
 * automaticamente, non è collegato a uno scheduler, non viene invocato
 * da alcun job periodico. Il rilascio (release()) resta un'azione
 * esplicita dell'operatore, tipicamente dopo aver verificato lo stato
 * reale lato provider (quando esisterà) — qui, con solo provider fake,
 * l'unico rischio reale è un worker di test/dry-run interrotto a metà,
 * ma il servizio è scritto per restare corretto anche quando la
 * pipeline sarà collegata a un provider reale.
 */
class StaleSendRecoveryService
{
    public function __construct(
        private readonly SendStateMachine $machine,
    ) {}

    /**
     * @return Collection<int, CommunicationSend>
     */
    public function findStale(int $olderThanMinutes = 30): Collection
    {
        return CommunicationSend::where('status', CommunicationSend::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes($olderThanMinutes))
            ->get();
    }

    /**
     * Riporta una riga bloccata a 'queued' per un futuro retry — SOLO su
     * richiesta esplicita del chiamante (comando artisan invocato a mano
     * da un operatore, mai automatico). Restituisce false se la riga non
     * è più 'sending' nel frattempo (nessuna eccezione: uno stato
     * cambiato concorrentemente è un esito normale, non un errore).
     */
    public function release(CommunicationSend $send, string $reason = 'manually released after stale sending window'): bool
    {
        if (! $this->machine->canTransition($send, CommunicationSend::STATUS_QUEUED)) {
            return false;
        }

        return $this->machine->transition($send, CommunicationSend::STATUS_QUEUED, [
            'failure_reason' => $reason,
        ]);
    }
}
