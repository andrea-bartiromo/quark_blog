<?php

namespace App\Jobs;

use App\Mail\PathContinuationMail;
use App\Models\Article;
use App\Models\CommunicationDelivery;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use App\Services\Communication\CommunicationDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Unico punto da cui una PathContinuationMail può davvero essere inviata —
 * sempre attraverso CommunicationDeliveryService::attemptSend() (Parte 10:
 * mai Mail::send() diretto da un hook di pubblicazione). Il job può essere
 * eseguito più volte dal layer di coda (duplicate job execution, Parte 16
 * scenario B): attemptSend() garantisce comunque al più un solo invio
 * reale per delivery_key, indipendentemente da quante volte questo handle()
 * viene eseguito.
 *
 * Tutte le condizioni ri-verificate qui (Percorso ancora attivo/updating,
 * articolo ancora pubblicato, iscrizione al Percorso ancora attiva) sono
 * DIVERSE dal ricontrollo del consenso globale già fatto da
 * CommunicationDeliveryService — quel livello conosce solo comm_subscribers,
 * non sa nulla di Percorsi. Una condizione qui non soddisfatta è un
 * fallimento onesto (mai un errore silenzioso, mai un secondo tentativo
 * automatico) tramite eccezione: CommunicationDeliveryService la registra
 * come 'failed' con il motivo esplicito.
 */
class SendPathContinuationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId) {}

    public function handle(CommunicationDeliveryService $service): void
    {
        $delivery = CommunicationDelivery::find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        $service->attemptSend($delivery, function () use ($delivery) {
            $this->send($delivery);
        });
    }

    private function send(CommunicationDelivery $delivery): void
    {
        $subscriber = $delivery->subscriber;

        if (! $subscriber) {
            throw new RuntimeException('subscriber_missing');
        }

        if ($delivery->notifiable_type !== ContentCluster::class || ! $delivery->notifiable_id) {
            throw new RuntimeException('notifiable_missing');
        }

        $cluster = ContentCluster::find($delivery->notifiable_id);

        // Il Percorso potrebbe essere stato concluso o disattivato tra la
        // registrazione della delivery e l'esecuzione di questo job — una
        // notifica in sospeso non deve mai partire per un Percorso che nel
        // frattempo non accetta più aggiornamenti (Parti 14/15).
        if (! $cluster || ! $cluster->acceptsPathSubscriptions()) {
            throw new RuntimeException('path_no_longer_updating');
        }

        if (! preg_match('/^article:(\d+):published$/', (string) $delivery->event_key, $matches)) {
            throw new RuntimeException('event_key_unparseable');
        }

        $article = Article::query()
            ->where('id', (int) $matches[1])
            ->where('status', Article::STATUS_PUBLISHED)
            ->first();

        // L'articolo potrebbe essere stato riportato a bozza, riprogrammato
        // o eliminato tra la registrazione e l'invio — l'email si costruisce
        // SOLO con un articolo verificato pubblico in questo preciso istante
        // (Parte 13), mai con lo stato letto al momento della registrazione.
        if (! $article) {
            throw new RuntimeException('article_no_longer_published');
        }

        $pathSubscription = ContentClusterSubscriber::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('content_cluster_id', $cluster->id)
            ->active()
            ->first();

        // Il subscriber potrebbe essersi disiscritto da QUESTO Percorso tra
        // la registrazione e l'invio — livello di consenso distinto dal
        // consenso globale già verificato da CommunicationDeliveryService.
        if (! $pathSubscription) {
            throw new RuntimeException('path_unsubscribed');
        }

        $unsubscribeUrl = route('percorsi.subscribe.unsubscribe', $pathSubscription->unsubscribe_token);

        Mail::to($subscriber->email)->send(
            new PathContinuationMail($cluster, $article, $unsubscribeUrl)
        );
    }
}
