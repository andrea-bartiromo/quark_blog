<?php

namespace App\Services\Communication;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationSubscriber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Motore di consegna idempotente GENERICO — il prerequisito costruito da
 * questa missione affinché una futura notifica Kairus (Percorsi "Avvisami
 * quando continua", Newsletter, notifiche future) possa dichiarare una
 * garanzia di consegna PRECISA, non un generico "a posto".
 *
 * Vocabolario esplicito (mai confuso tra loro in questo servizio):
 *
 *   - IDEMPOTENT JOB EXECUTION: NON è responsabilità di questo servizio —
 *     è una proprietà del layer di coda (Laravel `database` queue driver:
 *     retry_after, reserved_at). Un job può essere eseguito più di una
 *     volta dal layer di coda; questo servizio è la difesa che rende
 *     SICURA quella possibilità, non la elimina a monte.
 *   - ATOMIC DELIVERY CLAIM: la transizione pending→sending qui sotto,
 *     guardata da `WHERE status = 'pending'` — garantita dal motore DB
 *     (SQLite e MariaDB), mai da un lock applicativo in memoria.
 *   - AT-MOST-ONCE LOCAL DELIVERY ATTEMPT: ciò che questo servizio
 *     garantisce davvero — al più UN tentativo locale di $send() per
 *     ogni delivery_key, anche sotto dispatch duplicato o worker
 *     concorrenti.
 *   - IDEMPOTENT DELIVERY RECORD: registerDelivery() è idempotente per
 *     costruzione (insertOrIgnore contro un UNIQUE) — chiamarla N volte
 *     con la stessa identità produce sempre UNA sola riga logica.
 *   - EXACTLY-ONCE EXTERNAL EMAIL DELIVERY: MAI dichiarata da questo
 *     servizio. Se il processo muore dopo che Mail::send() è tornato
 *     internamente (il provider potrebbe aver già accettato il
 *     messaggio) ma prima che qui si registri l'esito, la riga resta in
 *     'sending' — una finestra di esito incerto onestamente
 *     rappresentata, mai nascosta né auto-risolta da questo layer (vedi
 *     il docblock di CommunicationDelivery per gli stati).
 */
class CommunicationDeliveryService
{
    /**
     * Registra (o recupera) l'intento di consegna per questa identità —
     * MAI invia nulla. Idempotente per costruzione: chiamabile quante
     * volte serve (dispatch duplicato, rerun, redelivery della coda)
     * senza mai produrre una seconda riga logica per la stessa identità,
     * grazie al vincolo UNIQUE su delivery_key applicato tramite
     * insertOrIgnore() — non un SELECT poi INSERT, che avrebbe una
     * finestra di race reale sotto concorrenza reale.
     *
     * Non verifica l'eleggibilità del subscriber: registrare che una
     * notifica era dovuta è un fatto distinto dal poterla davvero
     * inviare — quella verifica avviene, due volte, in attemptSend().
     */
    public function registerDelivery(
        string $channel,
        string $notificationType,
        CommunicationSubscriber $subscriber,
        ?Model $notifiable = null,
        ?string $eventKey = null
    ): CommunicationDelivery {
        $notifiableType = $notifiable?->getMorphClass();
        $notifiableId = $notifiable?->getKey() !== null ? (int) $notifiable->getKey() : null;

        $deliveryKey = CommunicationDelivery::computeDeliveryKey(
            $channel,
            $notificationType,
            $subscriber->id,
            $notifiableType,
            $notifiableId,
            $eventKey
        );

        $now = now();

        DB::table('communication_deliveries')->insertOrIgnore([
            'delivery_key' => $deliveryKey,
            'channel' => $channel,
            'notification_type' => $notificationType,
            'subscriber_id' => $subscriber->id,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'event_key' => $eventKey,
            'status' => CommunicationDelivery::STATUS_PENDING,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return CommunicationDelivery::where('delivery_key', $deliveryKey)->firstOrFail();
    }

    /**
     * Prova a inviare — l'UNICO punto in cui $send() può essere invocata.
     * Sicura sotto dispatch duplicato, worker concorrenti e retry dopo
     * completamento:
     *
     *   1. Ricarica $delivery (mai fidarsi dello stato in memoria — può
     *      essere cambiato da un altro processo dal momento in cui il
     *      chiamante l'ha ottenuta).
     *   2. Se non è più 'pending' (già sending/sent/failed altrove): nessun
     *      invio, ritorna lo stato attuale — copre sia "retry dopo
     *      completato" sia "un altro worker l'ha già presa in carico".
     *   3. Riverifica l'eleggibilità del subscriber ADESSO (il consenso può
     *      essere stato revocato dopo registerDelivery()) — se non
     *      eleggibile, transizione atomica pending→failed con motivo
     *      esplicito, $send() non viene MAI invocata.
     *   4. Claim atomico pending→sending: `UPDATE ... WHERE status =
     *      'pending'`. Se zero righe interessate, qualcun altro ha vinto la
     *      corsa nel frattempo — nessun invio da questo tentativo. Questa è
     *      la garanzia di concorrenza (Parte H): mai un lock applicativo in
     *      memoria, sempre l'atomicità della singola UPDATE del motore DB.
     *   5. Solo il vincitore del claim invoca $send(). Un'eccezione
     *      sincrona è un fallimento SICURO (nessuna ambiguità: sappiamo che
     *      non è partita) → 'failed', ritentabile esplicitamente. Un
     *      ritorno senza eccezioni → 'sent', terminale.
     *
     * @param  callable(): void  $send  Esegue il side effect reale (es.
     *                                  Mail::send/queue di una Mailable).
     *                                  Il servizio non conosce né costruisce
     *                                  mai il contenuto dell'email.
     *
     * Nullable in ritorno: la riga può essere sparita nel frattempo (es.
     * cascadeOnDelete se il subscriber viene eliminato del tutto, non solo
     * disiscritto) — in tal caso non c'è più nulla da riportare.
     */
    public function attemptSend(CommunicationDelivery $delivery, callable $send): ?CommunicationDelivery
    {
        $delivery = $delivery->fresh();

        if ($delivery === null || $delivery->status !== CommunicationDelivery::STATUS_PENDING) {
            return $delivery;
        }

        $subscriber = $delivery->subscriber;

        if ($subscriber === null || ! $subscriber->isEligibleForDelivery()) {
            $this->transition($delivery, CommunicationDelivery::STATUS_PENDING, [
                'status' => CommunicationDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => 'subscriber_ineligible',
            ]);

            return $delivery->fresh();
        }

        $claimed = $this->transition($delivery, CommunicationDelivery::STATUS_PENDING, [
            'status' => CommunicationDelivery::STATUS_SENDING,
            'claimed_at' => now(),
            'attempts' => $delivery->attempts + 1,
        ]);

        if (! $claimed) {
            // Un altro processo ha vinto il claim tra il refresh sopra e
            // qui — nessun invio da questo tentativo, mai una seconda
            // esecuzione di $send() per la stessa riga.
            return $delivery->fresh();
        }

        // Seconda (e ultima) riverifica del consenso: la prima (sopra)
        // può essere superata da una revoca arrivata proprio nella
        // finestra tra quel controllo e la vittoria del claim atomico —
        // per quanto stretta, è una finestra reale. Qui il claim è già
        // 'sending' (di nostra proprietà esclusiva): se il consenso non
        // c'è più, si chiude direttamente a 'failed' senza mai invocare
        // $send(), senza bisogno di un'altra transizione guardata (nessun
        // altro processo può competere su una riga che solo noi abbiamo
        // reclamato).
        if (! $subscriber->fresh()?->isEligibleForDelivery()) {
            CommunicationDelivery::query()->where('id', $delivery->id)->update([
                'status' => CommunicationDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => 'subscriber_ineligible',
                'updated_at' => now(),
            ]);

            return $delivery->fresh();
        }

        try {
            $send();

            $this->transition($delivery, CommunicationDelivery::STATUS_SENDING, [
                'status' => CommunicationDelivery::STATUS_SENT,
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->transition($delivery, CommunicationDelivery::STATUS_SENDING, [
                'status' => CommunicationDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'failure_reason' => substr($exception->getMessage(), 0, 2000),
            ]);

            throw $exception;
        }

        return $delivery->fresh();
    }

    /**
     * Ri-accoda esplicitamente una delivery 'failed' per un nuovo
     * tentativo — mai automatico: è una decisione deliberata del
     * chiamante (es. un comando di retry manuale), non qualcosa che
     * questo servizio fa da solo. Atomico allo stesso modo del claim
     * principale: solo una transizione failed→pending vince se più
     * chiamanti la richiedono contemporaneamente.
     */
    public function retryFailed(CommunicationDelivery $delivery): ?CommunicationDelivery
    {
        $delivery = $delivery->fresh();

        if ($delivery === null) {
            return null;
        }

        $retried = $this->transition($delivery, CommunicationDelivery::STATUS_FAILED, [
            'status' => CommunicationDelivery::STATUS_PENDING,
        ]);

        return $retried ? $delivery->fresh() : null;
    }

    /**
     * UPDATE atomico guardato dallo stato atteso — il meccanismo condiviso
     * dietro ogni transizione di questo servizio. Ritorna true solo se
     * QUESTA chiamata ha davvero effettuato la transizione (una sola riga
     * interessata): è così che due chiamate concorrenti sulla stessa riga
     * hanno sempre un solo vincitore, garantito dal motore DB.
     */
    private function transition(CommunicationDelivery $delivery, string $expectedStatus, array $attributes): bool
    {
        $affected = CommunicationDelivery::query()
            ->where('id', $delivery->id)
            ->where('status', $expectedStatus)
            ->update($attributes + ['updated_at' => now()]);

        return $affected === 1;
    }
}
