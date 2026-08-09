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
