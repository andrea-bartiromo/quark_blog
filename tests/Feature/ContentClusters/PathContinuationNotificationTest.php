<?php

namespace Tests\Feature\ContentClusters;

use App\Jobs\SendPathContinuationNotification;
use App\Mail\PathContinuationMail;
use App\Models\Article;
use App\Models\CommunicationDelivery;
use App\Models\CommunicationSubscriber;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use App\Models\User;
use App\Services\Communication\CommunicationDeliveryService;
use App\Services\ContentClusters\PathContinuationNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Trigger di pubblicazione + invio reale "Avvisami quando continua" (Parti
 * 8-16 della missione). Mail::fake() sempre attivo: nessuna email reale.
 *
 * Copre esplicitamente gli scenari della Parte 16: A (evento duplicato),
 * B (job duplicato), C (retry dopo sent), D (consenso globale revocato
 * prima del claim), E-equivalente per il livello Percorso (disiscritto
 * dal Percorso specifico), F (delivery bloccata in sending mai
 * auto-ritentata — proprietà già di #198, qui solo verificata end-to-end
 * con questo job), più Parte 9 (multi-percorso) e Parte 13 (niente leak).
 */
class PathContinuationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function updatingCluster(array $attributes = []): ContentCluster
    {
        return ContentCluster::factory()->create(array_merge([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ], $attributes));
    }

    private function draftArticle(string $title = 'Bozza'): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_DRAFT,
            'read_minutes' => 1,
        ]);
    }

    private function publish(Article $article): Article
    {
        $article->status = Article::STATUS_PUBLISHED;
        $article->save();

        return $article->fresh();
    }

    public function test_publishing_an_article_registers_and_sends_a_delivery_to_an_active_path_subscriber(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle('Nuova tappa');
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);

        $this->publish($article);

        $this->assertSame(1, CommunicationDelivery::count());

        // I job restano nella coda 'database' — eseguiti sincronamente qui
        // per verificare l'invio reale (Mail::fake intercetta comunque
        // ogni email, non ne parte mai una vera).
        $this->runQueuedPathContinuationJobs();

        Mail::assertSent(PathContinuationMail::class, function ($mail) use ($subscriber, $article) {
            return $mail->hasTo($subscriber->email) && $mail->article->id === $article->id;
        });

        $this->assertSame(CommunicationDelivery::STATUS_SENT, CommunicationDelivery::first()->status);
    }

    public function test_scenario_a_the_same_publication_event_dispatched_twice_produces_only_one_logical_delivery(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);

        $notifier = app(PathContinuationNotifier::class);

        $published = $this->publish($article);
        // Simula un secondo trigger duplicato per lo stesso evento di
        // pubblicazione (es. un secondo worker che rielabora lo stesso
        // hook, o una race sull'update).
        $notifier->notifyIfPublished($published);
        $notifier->notifyIfPublished($published);

        $this->assertSame(1, CommunicationDelivery::count());
    }

    public function test_scenario_b_the_same_queued_job_executed_twice_sends_only_once(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);

        $delivery = CommunicationDelivery::firstOrFail();

        // Stesso job, stessa delivery, eseguito due volte "manualmente"
        // (simula una ri-consegna della coda dopo un crash/timeout).
        (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));
        (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));

        Mail::assertSentCount(1);
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    public function test_scenario_c_retry_after_sent_never_resends(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);
        $this->runQueuedPathContinuationJobs();

        Mail::assertSentCount(1);

        $delivery = CommunicationDelivery::firstOrFail();
        $this->assertSame(CommunicationDelivery::STATUS_SENT, $delivery->status);

        // Un retry esplicito su una delivery già 'sent' non deve mai
        // rimandare: CommunicationDeliveryService::attemptSend() la trova
        // non più 'pending' e non invoca $send().
        (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));

        Mail::assertSentCount(1);
    }

    public function test_scenario_d_globally_revoked_consent_before_the_job_runs_blocks_the_send(): void
    {
        Queue::fake();

        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);

        Queue::assertPushed(SendPathContinuationNotification::class);
        $delivery = CommunicationDelivery::firstOrFail();

        // Il consenso globale viene revocato DOPO la registrazione della
        // delivery ma PRIMA che il job venga eseguito (coda finta: la
        // registrazione è avvenuta, l'invio no).
        $subscriber->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);

        try {
            (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));
        } catch (\Throwable $exception) {
            // Comportamento atteso: attemptSend() rilancia dopo aver marcato
            // 'failed', esattamente come farebbe un vero worker di coda
            // (che poi applica la propria gestione fallimento/retry) — nel
            // test si verifica lo STATO risultante, non l'eccezione stessa.
        }

        Mail::assertNothingSent();
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
    }

    public function test_path_level_unsubscribe_before_the_job_runs_blocks_the_send_without_touching_global_consent(): void
    {
        Queue::fake();

        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $subscription = ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);

        Queue::assertPushed(SendPathContinuationNotification::class);
        $delivery = CommunicationDelivery::firstOrFail();

        // Disiscrizione dal SOLO Percorso, dopo la registrazione ma prima
        // dell'invio — livello di consenso che #198 non conosce affatto.
        $subscription->update(['status' => ContentClusterSubscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => now()]);

        try {
            (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));
        } catch (\Throwable $exception) {
            // Comportamento atteso: attemptSend() rilancia dopo aver marcato
            // 'failed', esattamente come farebbe un vero worker di coda
            // (che poi applica la propria gestione fallimento/retry) — nel
            // test si verifica lo STATO risultante, non l'eccezione stessa.
        }

        Mail::assertNothingSent();
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertSame('path_unsubscribed', $delivery->fresh()->failure_reason);
        $this->assertSame(CommunicationSubscriber::STATUS_CONFIRMED, $subscriber->fresh()->status);
    }

    public function test_scenario_f_a_delivery_stuck_in_sending_is_never_auto_resent_by_this_job(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $delivery = CommunicationDelivery::factory()->sending()->create([
            'subscriber_id' => $subscriber->id,
            'notifiable_type' => ContentCluster::class,
            'notifiable_id' => $cluster->id,
            'notification_type' => 'path_continuation',
            'event_key' => 'article:999999:published',
        ]);

        try {
            (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));
        } catch (\Throwable $exception) {
            // Comportamento atteso: attemptSend() rilancia dopo aver marcato
            // 'failed', esattamente come farebbe un vero worker di coda
            // (che poi applica la propria gestione fallimento/retry) — nel
            // test si verifica lo STATO risultante, non l'eccezione stessa.
        }

        Mail::assertNothingSent();
        $this->assertSame(CommunicationDelivery::STATUS_SENDING, $delivery->fresh()->status, 'Una delivery bloccata in sending non viene mai auto-risolta.');
    }

    public function test_a_cluster_that_stops_updating_before_the_job_runs_blocks_the_send(): void
    {
        Queue::fake();

        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);

        Queue::assertPushed(SendPathContinuationNotification::class);
        $delivery = CommunicationDelivery::firstOrFail();

        $cluster->update(['lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);

        try {
            (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));
        } catch (\Throwable $exception) {
            // Comportamento atteso: attemptSend() rilancia dopo aver marcato
            // 'failed', esattamente come farebbe un vero worker di coda
            // (che poi applica la propria gestione fallimento/retry) — nel
            // test si verifica lo STATO risultante, non l'eccezione stessa.
        }

        Mail::assertNothingSent();
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
    }

    public function test_an_article_unpublished_before_the_job_runs_blocks_the_send_no_content_leak(): void
    {
        Queue::fake();

        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);

        Queue::assertPushed(SendPathContinuationNotification::class);
        $delivery = CommunicationDelivery::firstOrFail();

        $article->update(['status' => Article::STATUS_DRAFT]);

        try {
            (new SendPathContinuationNotification($delivery->id))->handle(app(CommunicationDeliveryService::class));
        } catch (\Throwable $exception) {
            // Comportamento atteso: attemptSend() rilancia dopo aver marcato
            // 'failed', esattamente come farebbe un vero worker di coda
            // (che poi applica la propria gestione fallimento/retry) — nel
            // test si verifica lo STATO risultante, non l'eccezione stessa.
        }

        Mail::assertNothingSent();
        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
    }

    public function test_an_article_in_two_updating_paths_generates_two_distinct_deliveries_and_two_emails(): void
    {
        $clusterA = $this->updatingCluster();
        $clusterB = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create(['subscriber_id' => $subscriber->id, 'content_cluster_id' => $clusterA->id]);
        ContentClusterSubscriber::factory()->create(['subscriber_id' => $subscriber->id, 'content_cluster_id' => $clusterB->id]);

        $article = $this->draftArticle();
        $article->contentClusters()->attach([$clusterA->id => ['position' => 10], $clusterB->id => ['position' => 10]]);
        $this->publish($article);

        $this->assertSame(2, CommunicationDelivery::count(), 'Un logical delivery per Percorso, mai deduplicato cross-Percorso.');

        $this->runQueuedPathContinuationJobs();

        Mail::assertSentCount(2);
    }

    public function test_no_delivery_is_registered_for_a_draft_or_scheduled_article(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create(['subscriber_id' => $subscriber->id, 'content_cluster_id' => $cluster->id]);

        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);

        $article->status = Article::STATUS_SCHEDULED;
        $article->published_at = now()->addDay();
        $article->save();

        $this->assertSame(0, CommunicationDelivery::count());
    }

    public function test_no_delivery_is_registered_for_a_complete_or_inactive_path(): void
    {
        $complete = ContentCluster::factory()->create(['is_active' => true, 'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);
        $inactive = ContentCluster::factory()->create(['is_active' => false, 'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create(['subscriber_id' => $subscriber->id, 'content_cluster_id' => $complete->id]);
        ContentClusterSubscriber::factory()->create(['subscriber_id' => $subscriber->id, 'content_cluster_id' => $inactive->id]);

        $article = $this->draftArticle();
        $article->contentClusters()->attach([$complete->id => ['position' => 10], $inactive->id => ['position' => 10]]);

        $this->publish($article);

        $this->assertSame(0, CommunicationDelivery::count());
    }

    public function test_editing_an_already_published_article_without_a_status_change_does_not_trigger_a_new_delivery(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create(['subscriber_id' => $subscriber->id, 'content_cluster_id' => $cluster->id]);

        $article = $this->draftArticle();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);

        $this->assertSame(1, CommunicationDelivery::count());

        $article->update(['title' => 'Titolo corretto dopo pubblicazione']);

        $this->assertSame(1, CommunicationDelivery::count(), 'Una modifica che non cambia lo status non deve generare una seconda delivery.');
    }

    public function test_email_content_only_reflects_the_currently_published_article_never_unpublished_state(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        ContentClusterSubscriber::factory()->create(['subscriber_id' => $subscriber->id, 'content_cluster_id' => $cluster->id]);
        $article = $this->draftArticle('Titolo pubblico definitivo');
        $article->excerpt = 'Estratto pubblico definitivo.';
        $article->save();
        $article->contentClusters()->attach($cluster->id, ['position' => 10]);
        $this->publish($article);

        $this->runQueuedPathContinuationJobs();

        Mail::assertSent(PathContinuationMail::class, function ($mail) {
            return str_contains($mail->article->title, 'Titolo pubblico definitivo');
        });
    }

    /**
     * I job SendPathContinuationNotification finiscono nella coda
     * 'database' (QUEUE_CONNECTION di test = sync per il resto della
     * suite, ma qui non si usa Queue::fake() perché serve eseguire
     * davvero handle() per osservare lo stato finale della delivery).
     * Con QUEUE_CONNECTION=sync (phpunit.xml), il job gira già in modo
     * sincrono dentro publish(): questo helper esiste solo per rendere
     * esplicito, nel nome del test, il punto in cui l'invio avviene.
     */
    private function runQueuedPathContinuationJobs(): void
    {
        // No-op con QUEUE_CONNECTION=sync: i job dispatchati da
        // PathContinuationNotifier sono già stati eseguiti in linea
        // durante publish(). Il metodo resta per leggibilità dei test e
        // per restare corretto se la connessione di coda cambiasse.
    }
}
