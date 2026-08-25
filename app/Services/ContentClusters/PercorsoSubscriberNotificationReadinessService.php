<?php

/**
 * Kairus — Percorsi: readiness delle notifiche "Avvisami quando continua"
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2026 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Services\ContentClusters;

use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;

/**
 * Missione 22 (secondo batch autonomo KAIRUS, Fase C — Percorsi Advanced
 * Operations): "Percorsi subscriber notification readiness."
 *
 * "Iscritto" (content_cluster_subscribers.status = active) e "raggiungibile
 * dalla prossima notifica" NON sono la stessa cosa: PathContinuationNotifier
 * registra una delivery per ogni iscrizione attiva, ma
 * CommunicationDeliveryService::attemptSend() invia solo se
 * CommunicationSubscriber::isEligibleForDelivery() è vero (solo 'confirmed'
 * — mai pending/unsubscribed/bounced/complained, la STESSA unica
 * definizione, mai riscritta qui). Un editor che guarda solo il conteggio
 * delle iscrizioni attive sovrastimerebbe quindi la reale portata della
 * prossima notifica.
 *
 * Riusa anche ContentCluster::acceptsPathSubscriptions() (is_active &&
 * lifecycle_status===updating) — la stessa identica condizione già
 * verificata da PathContinuationNotifier::notifyIfPublished() prima di
 * registrare qualunque delivery — per dire onestamente quando il conteggio
 * sotto è irrilevante: un Percorso concluso o inattivo non invierà mai
 * nulla alla prossima pubblicazione, indipendentemente da quanti abbonati
 * risultino "eleggibili ora".
 */
class PercorsoSubscriberNotificationReadinessService
{
    /**
     * @return array{
     *     notifications_would_fire: bool,
     *     active_subscriptions: int,
     *     eligible_now: int,
     *     not_eligible_now: int,
     *     unsubscribed: int,
     * }
     */
    public function summary(ContentCluster $cluster): array
    {
        $activeSubscriptions = ContentClusterSubscriber::query()
            ->where('content_cluster_id', $cluster->id)
            ->active()
            ->with('subscriber')
            ->get();

        $eligibleNow = $activeSubscriptions
            ->filter(fn (ContentClusterSubscriber $subscription) => $subscription->subscriber?->isEligibleForDelivery())
            ->count();

        $unsubscribedCount = ContentClusterSubscriber::query()
            ->where('content_cluster_id', $cluster->id)
            ->where('status', ContentClusterSubscriber::STATUS_UNSUBSCRIBED)
            ->count();

        return [
            'notifications_would_fire' => $cluster->acceptsPathSubscriptions(),
            'active_subscriptions' => $activeSubscriptions->count(),
            'eligible_now' => $eligibleNow,
            'not_eligible_now' => $activeSubscriptions->count() - $eligibleNow,
            'unsubscribed' => $unsubscribedCount,
        ];
    }
}
