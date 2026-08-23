<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignActivityLog;
use App\Models\CommunicationSend;
use RuntimeException;

/**
 * Recipient Snapshot + Campaign Freeze — secondo tassello. Il primo
 * (Recipient Snapshot) esiste già ed è RecipientSnapshotService: crea le
 * righe comm_sends. Questo servizio aggiunge il pezzo che mancava —
 * "congelare" una campagna pronta, così che contenuto/template/mittente
 * e l'elenco destinatari non possano più cambiare sotto una campagna in
 * attesa di invio.
 *
 * Cosa blocca il congelamento (verificato qui e imposto altrove):
 *   - CommunicationCampaignController::update() rifiuta ogni modifica a
 *     una campagna congelata (titolo/oggetto/preheader/corpo/template/
 *     mittente/progetto) — vedi il suo guard esplicito.
 *   - RecipientSnapshotService::canPrepare() torna false per una
 *     campagna congelata: "Prepara destinatari" non aggiunge più righe,
 *     anche se nuovi iscritti si confermano dopo il congelamento.
 *
 * Cosa il congelamento NON fa (deliberatamente):
 *   - Non tocca lo status della campagna (draft/scheduled/...): è
 *     ortogonale alla CampaignStateMachine esistente.
 *   - Non autorizza mai l'invio a un destinatario congelato che si
 *     disiscrive DOPO il congelamento — quella garanzia esiste già,
 *     invariata, in CampaignDeliveryOrchestrator::revalidate(), che
 *     rilegge SEMPRE lo stato fresco di iscritto/campagna/mittente dal
 *     DB al momento del claim, mai fidandosi di una riga comm_sends
 *     'queued' né tantomeno di questo congelamento. Una riga congelata
 *     "queued" per un iscritto poi disiscritto risolve comunque a
 *     'failed' (subscriber_not_eligible) quando l'invio reale la
 *     processa — vedi CampaignFreezeZeroSendRegressionTest.
 *
 * Idempotente per costruzione: congelare una campagna già congelata è un
 * no-op silenzioso (nessuna doppia scrittura, nessuna doppia riga di
 * audit log) — sicuro da richiamare più volte, incluso sotto un doppio
 * click admin o un retry di rete.
 */
class CampaignFreezeService
{
    public function __construct(
        private readonly CampaignPreflightService $preflight,
    ) {}

    /**
     * Una campagna è congelabile solo se già pronta per un invio di
     * test (stessa soglia di readiness già usata da preflight/dry-run —
     * niente di nuovo da ridefinire qui): mittente attivo, oggetto e
     * corpo valorizzati, almeno un destinatario preparato, stato ancora
     * draft/scheduled. Mai congelabile due volte.
     */
    public function canFreeze(CommunicationCampaign $campaign): bool
    {
        if ($campaign->trashed() || $campaign->isFrozen()) {
            return false;
        }

        return $this->preflight->assess($campaign)->isReady();
    }

    /**
     * @return bool true se questa chiamata ha effettivamente congelato la
     *              campagna, false se era già congelata (no-op idempotente).
     *
     * @throws RuntimeException se la campagna non è pronta e non è già
     *                          congelata — un errore di programmazione
     *                          del chiamante (l'admin controller verifica
     *                          canFreeze() prima e non arriva mai qui in
     *                          quel caso), non un esito operativo normale.
     */
    public function freeze(CommunicationCampaign $campaign, ?int $actorId): bool
    {
        if ($campaign->isFrozen()) {
            return false;
        }

        if (! $this->canFreeze($campaign)) {
            throw new RuntimeException(
                "Impossibile congelare la campagna #{$campaign->id}: non è pronta (verifica pre-invio non superata) oppure non più in uno stato congelabile."
            );
        }

        $recipientCount = CommunicationSend::where('campaign_id', $campaign->id)->count();

        // Identificatore della versione di contenuto congelata — mai il
        // contenuto stesso (niente testo/HTML nel log di audit): solo
        // l'id della versione template immutabile già referenziata da
        // template_version_id, oppure un'etichetta esplicita quando la
        // campagna non usa alcun template (corpo scritto a mano).
        $contentVersion = $campaign->template_version_id !== null
            ? "template_version:{$campaign->template_version_id}"
            : 'contenuto_manuale';

        $campaign->forceFill([
            'frozen_at' => now(),
            'frozen_by' => $actorId,
        ])->save();

        CommunicationCampaignActivityLog::record(
            campaign: $campaign,
            subjectType: 'freeze',
            subjectId: $campaign->id,
            subjectTitle: $campaign->title,
            action: "Campagna congelata: {$recipientCount} destinatari e contenuto bloccati",
            userId: $actorId,
            newValue: (string) $recipientCount,
            reason: $contentVersion,
        );

        return true;
    }
}
