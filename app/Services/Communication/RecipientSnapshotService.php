<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use Illuminate\Support\Str;

/**
 * Newsletter 2.0 — primo incremento verso l'invio reale: crea lo snapshot
 * dei destinatari di una campagna SENZA inviare alcuna email. Popola
 * `comm_sends` con una riga (status=queued) per ogni iscritto confermato
 * non ancora presente per questa campagna — l'invio vero e proprio è un
 * incremento successivo, fuori scope qui.
 *
 * Idempotente per costruzione: si appoggia al vincolo unique(campaign_id,
 * subscriber_id) già presente in `comm_sends` fin dalla sua migration
 * originale (pensato esattamente per questo scopo, mai usato finora).
 * Rieseguibile in sicurezza in qualunque momento prima dell'invio reale:
 * un secondo run aggiunge solo gli iscritti confermati nel frattempo,
 * senza mai duplicare o toccare le righe già presenti.
 *
 * Additivo, non riconciliante: se un iscritto già "snapshottato" si
 * disiscrive DOPO questo run, la sua riga in comm_sends resta 'queued'
 * finché non viene ri-verificata — quella verifica è deliberatamente
 * demandata al futuro step di invio reale (che dovrà ricontrollare lo
 * stato dell'iscritto al momento di spedire, non fidarsi dello snapshot),
 * non a questo servizio. Rieseguire "Prepara destinatari" NON rimuove mai
 * righe esistenti, nemmeno per iscritti nel frattempo disiscritti.
 *
 * INVARIANTI CHE IL FUTURO STEP DI INVIO REALE DOVRÀ RISPETTARE (non
 * implementate qui — questo servizio si ferma allo snapshot):
 *
 *   1. Iscritto disiscritto DOPO lo snapshot: la riga resta 'queued'.
 *      Il sender deve riverificare lo stato dell'iscritto (confirmed)
 *      SUBITO PRIMA di spedire, non fidarsi del fatto che la riga esista.
 *   2. Iscritto 'pending' (mai confermato): non riceve mai una riga da
 *      questo servizio (solo confirmed() è eleggibile) — ma se in futuro
 *      un iscritto confermato potesse mai tornare 'pending', il sender
 *      deve comunque validare lo stato al momento dell'invio, mai
 *      assumerlo dalla sola presenza della riga 'queued'.
 *   3. Iscritto cancellato DOPO lo snapshot: comm_sends.subscriber_id ha
 *      cascadeOnDelete() dalla sua migration originale — la riga sparisce
 *      automaticamente, il sender non troverà mai una riga orfana da
 *      gestire esplicitamente (verificato in
 *      RecipientSnapshotRaceAndScaleTest).
 *   4. Email cambiata DOPO lo snapshot: comm_sends non denormalizza
 *      l'email — il sender userà sempre l'email CORRENTE del subscriber
 *      (tramite la relazione, mai un valore congelato), salvo una futura
 *      decisione esplicita di denormalizzarla (non presa qui).
 *   5. Contenuto della campagna modificato DOPO lo snapshot: snapshot dei
 *      destinatari e contenuto della campagna sono concetti
 *      DELIBERATAMENTE separati — comm_sends non contiene né referenzia
 *      alcuna copia del contenuto. Nessun "content snapshot" implicito è
 *      stato introdotto in questa missione.
 *   6. Invio ripetuto/doppio: il futuro sistema di invio dovrà avere una
 *      propria guardia di idempotenza (a livello di singolo Send, non
 *      solo di snapshot) — questo servizio fornisce solo la base
 *      (vincolo unique(campaign_id, subscriber_id), già usato qui).
 *   7. Retry sugli invii falliti: comm_sends ha già le colonne
 *      `status` (queued/sent/delivered/bounced/failed), `attempts`
 *      (unsignedInteger, default 0) e `failure_reason` — predisposte
 *      dalla migration originale esattamente per supportare un futuro
 *      meccanismo di retry, mai popolate/incrementate da questo servizio
 *      (attempts resta sempre 0 dopo "Prepara destinatari").
 */
class RecipientSnapshotService
{
    /**
     * Stati della campagna da cui è consentito preparare i destinatari:
     * mai su una campagna già in invio/completata/fallita/annullata, dove
     * uno snapshot nuovo non avrebbe senso operativo.
     */
    private const ELIGIBLE_CAMPAIGN_STATUSES = [
        CommunicationCampaign::STATUS_DRAFT,
        CommunicationCampaign::STATUS_SCHEDULED,
    ];

    public function canPrepare(CommunicationCampaign $campaign): bool
    {
        // comm_campaigns usa SoftDeletes: "Elimina campagna" nell'admin è
        // una UPDATE (deleted_at), non una DELETE — la cascadeOnDelete()
        // FK su comm_sends.campaign_id non scatta mai per questo percorso.
        // Il binding di rotta esclude di default un modello trashed (404),
        // ma questo controllo resta necessario per qualunque chiamante
        // futuro che carichi un'istanza trashed esplicitamente (es. un
        // comando/job), non solo dietro al 404 della rotta admin.
        if ($campaign->trashed()) {
            return false;
        }

        // Congelamento (CampaignFreezeService): una campagna congelata ha
        // il suo elenco destinatari deliberatamente bloccato al momento
        // del congelamento — rieseguire "Prepara destinatari" dopo non
        // deve più aggiungere righe, indipendentemente da quanti nuovi
        // iscritti si sono confermati nel frattempo. Stessa guardia
        // messa qui (non solo nel controller admin) per restare valida
        // per qualunque chiamante futuro, come trashed() sopra.
        if ($campaign->isFrozen()) {
            return false;
        }

        return in_array($campaign->status, self::ELIGIBLE_CAMPAIGN_STATUSES, true);
    }

    /**
     * @return array{added:int, already_present:int, eligible_total:int}
     */
    public function prepare(CommunicationCampaign $campaign): array
    {
        if (! $this->canPrepare($campaign)) {
            throw new \RuntimeException(
                "Impossibile preparare i destinatari per una campagna con stato '{$campaign->status}'."
            );
        }

        $eligibleIds = CommunicationSubscriber::confirmed()->pluck('id');

        $alreadyPresentIds = CommunicationSend::where('campaign_id', $campaign->id)
            ->whereIn('subscriber_id', $eligibleIds)
            ->pluck('subscriber_id');

        $toInsertIds = $eligibleIds->diff($alreadyPresentIds);

        $now = now();

        // insertOrIgnore in blocchi: nessuna query per riga (a differenza di
        // firstOrCreate in loop), sicuro sotto race condition grazie al
        // vincolo unique DB-level — due richieste "Prepara destinatari"
        // concorrenti sulla stessa campagna non possono mai produrre righe
        // duplicate, la seconda si limita a ignorare i conflitti.
        $added = 0;

        foreach ($toInsertIds->chunk(500) as $chunk) {
            $rows = $chunk->map(fn (int $subscriberId) => [
                'uuid' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriberId,
                'status' => CommunicationSend::STATUS_QUEUED,
                'queued_at' => $now,
                'attempts' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            $added += CommunicationSend::query()->insertOrIgnore($rows);
        }

        return [
            'added' => $added,
            'already_present' => $alreadyPresentIds->count(),
            'eligible_total' => $eligibleIds->count(),
        ];
    }
}
