<?php

namespace App\Services\ContentClusters;

use App\Jobs\SendPathContinuationNotification;
use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use App\Services\Communication\CommunicationDeliveryService;

/**
 * Trigger di pubblicazione per "Avvisami quando continua" (Parte 8/9/10
 * della missione). Chiamato SOLO dal punto reale e comune di pubblicazione
 * (vedi Article::booted()) — mai da un controller, mai duplicato altrove.
 *
 * Registra soltanto: non invia mai nulla direttamente (vedi
 * SendPathContinuationNotification per il vero invio, sempre tramite
 * CommunicationDeliveryService::attemptSend()).
 */
class PathContinuationNotifier
{
    public function __construct(private readonly CommunicationDeliveryService $deliveryService) {}

    public function notifyIfPublished(Article $article): void
    {
        if ($article->status !== Article::STATUS_PUBLISHED) {
            return;
        }

        // Solo i Percorsi attivi E in aggiornamento generano una
        // notifica — un Percorso concluso o inattivo non deve mai far
        // partire un invio (Parti 14/15), anche se l'articolo che ha
        // appena raggiunto lo stato pubblicato vi appartiene.
        $clusters = $article->contentClusters()
            ->where('content_clusters.is_active', true)
            ->where('content_clusters.lifecycle_status', ContentCluster::LIFECYCLE_UPDATING)
            ->get(['content_clusters.id']);

        if ($clusters->isEmpty()) {
            return;
        }

        // event_key include l'ID dell'articolo: stesso subscriber + stesso
        // Percorso ma articolo diverso => identità di consegna diversa,
        // mai deduplicata. Un articolo appartenente a più Percorsi
        // aggiornati genera un logical delivery per Percorso (Parte 9):
        // notifiable_id differisce, quindi non c'è mai collisione
        // cross-Percorso nella stessa delivery_key.
        $eventKey = 'article:'.$article->id.':published';

        foreach ($clusters as $cluster) {
            $subscriptions = ContentClusterSubscriber::query()
                ->where('content_cluster_id', $cluster->id)
                ->active()
                ->with('subscriber')
                ->get();

            foreach ($subscriptions as $subscription) {
                if (! $subscription->subscriber) {
                    continue;
                }

                $delivery = $this->deliveryService->registerDelivery(
                    'email',
                    'path_continuation',
                    $subscription->subscriber,
                    $cluster,
                    $eventKey
                );

                SendPathContinuationNotification::dispatch($delivery->id);
            }
        }
    }
}
