<?php

namespace App\Services\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use Illuminate\Support\Facades\Route;

/**
 * N2.8 — schermata di verifica pre-invio (preflight). Sola lettura: non
 * crea/modifica/marca mai nulla, ricalcolabile a piacere. Risponde a una
 * sola domanda — "se qualcuno premesse un ipotetico bottone invio adesso,
 * cosa lo bloccherebbe, e cosa andrebbe comunque controllato?" — senza
 * fornire quel bottone: nessun metodo qui avvia mai una consegna, reale o
 * fake. Il passo successivo della pipeline (dry-run, N2.9) è un incremento
 * separato che userà CampaignDeliveryOrchestrator con un provider fake.
 *
 * Le due categorie di esito rispecchiano deliberatamente la revalidation
 * a runtime già presente in CampaignDeliveryOrchestrator::revalidate():
 * un "blocco" qui è qualcosa che impedirebbe l'invio a OGNI destinatario
 * (mittente/oggetto/contenuto/stato campagna), un "avviso" segnala un
 * disallineamento recuperabile (es. iscritti non più confermati da quando
 * sono stati preparati) che il motore di invio gestirà comunque da solo
 * al momento della revalidation — qui è solo visibilità anticipata, non
 * un'azione da compiere.
 */
class CampaignPreflightService
{
    public function assess(CommunicationCampaign $campaign): CampaignPreflightReport
    {
        $campaign->loadMissing('senderProfile');

        $preparedCount = CommunicationSend::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CommunicationSend::STATUS_QUEUED)
            ->count();

        // Righe preparate il cui iscritto non è più 'confirmed' ORA (si è
        // disiscritto, o non lo è mai più tornato ad essere) — non un
        // problema da correggere qui: il motore di invio le salterà da
        // solo via revalidation al momento del claim. Solo visibilità.
        $staleCount = CommunicationSend::query()
            ->where('comm_sends.campaign_id', $campaign->id)
            ->where('comm_sends.status', CommunicationSend::STATUS_QUEUED)
            ->join('comm_subscribers', 'comm_subscribers.id', '=', 'comm_sends.subscriber_id')
            ->where('comm_subscribers.status', '!=', CommunicationSubscriber::STATUS_CONFIRMED)
            ->count();

        $eligibleTotal = CommunicationSubscriber::confirmed()->count();

        // Iscritti confermati ORA che non hanno ancora alcuna riga
        // comm_sends per questa campagna — set-based (NOT EXISTS), mai un
        // caricamento riga-per-riga, per restare corretto anche a 10k+.
        $notYetPreparedCount = CommunicationSubscriber::confirmed()
            ->whereDoesntHave('sends', fn ($q) => $q->where('campaign_id', $campaign->id))
            ->count();

        $unsubscribeRouteAvailable = Route::has('comunicazione.disiscrizione.conferma');

        $sender = $campaign->senderProfile;
        $hasSubject = filled($campaign->subject);
        $hasBody = filled($campaign->content['body'] ?? null);

        $blockingErrors = [];

        if (! $sender) {
            $blockingErrors[] = 'Nessun mittente collegato alla campagna.';
        } elseif ($sender->status !== CommunicationSenderProfile::STATUS_ACTIVE) {
            $blockingErrors[] = "Il mittente collegato («{$sender->name}») è archiviato.";
        }

        if (! $hasSubject) {
            $blockingErrors[] = "Manca l'oggetto della campagna.";
        }

        if (! $hasBody) {
            $blockingErrors[] = 'Manca il contenuto della campagna.';
        }

        if ($preparedCount === 0) {
            $blockingErrors[] = 'Nessun destinatario preparato: esegui "Prepara destinatari" prima di procedere.';
        }

        if (! in_array($campaign->status, [CommunicationCampaign::STATUS_DRAFT, CommunicationCampaign::STATUS_SCHEDULED], true)) {
            $statusLabel = CommunicationCampaign::statusOptions()[$campaign->status] ?? $campaign->status;
            $blockingErrors[] = "La campagna ha stato «{$statusLabel}»: non è più in una fase di preparazione.";
        }

        if (! $unsubscribeRouteAvailable) {
            $blockingErrors[] = 'Il flusso di disiscrizione pubblico non risulta disponibile.';
        }

        $warnings = [];

        if ($staleCount > 0) {
            $warnings[] = "{$staleCount} destinatario/i preparato/i non è/sono più un iscritto confermato — verrà/verranno saltato/i automaticamente al momento dell'invio, nessuna azione richiesta.";
        }

        if ($notYetPreparedCount > 0) {
            $warnings[] = "{$notYetPreparedCount} iscritto/i confermato/i non ha/hanno ancora una riga preparata per questa campagna. Riesegui \"Prepara destinatari\" per includerlo/i.";
        }

        if (blank($campaign->preheader)) {
            $warnings[] = 'Nessun preheader impostato.';
        }

        $readiness = empty($blockingErrors)
            ? CampaignPreflightReport::READY_FOR_TEST_SEND
            : CampaignPreflightReport::NOT_READY;

        return new CampaignPreflightReport(
            readiness: $readiness,
            preparedCount: $preparedCount,
            staleCount: $staleCount,
            notYetPreparedCount: $notYetPreparedCount,
            eligibleTotal: $eligibleTotal,
            unsubscribeRouteAvailable: $unsubscribeRouteAvailable,
            blockingErrors: $blockingErrors,
            warnings: $warnings,
        );
    }
}
