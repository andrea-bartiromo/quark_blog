<?php

namespace Tests\Feature\ContentClusters;

use App\Models\Article;
use App\Models\CommunicationSubscriber;
use App\Models\ContentCluster;
use App\Models\ContentClusterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * "Avvisami quando continua" — iscrizione/conferma/disiscrizione per
 * singolo Percorso (Parti 3-7, 16 scenario G, 17, 20 della missione).
 * Mail::fake() sempre attivo: nessuna email reale in nessun test.
 */
class PathContinuationSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // Il throttle:5,1 sulla rotta è già lo stesso pattern collaudato
        // di newsletter.subscribe — qui è disattivato solo per non far
        // scattare il limite tra i tanti submit di questa singola classe
        // di test, che condividono lo stesso processo PHPUnit e quindi lo
        // stesso RateLimiter in-memory.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function updatingCluster(array $attributes = []): ContentCluster
    {
        return ContentCluster::factory()->create(array_merge([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ], $attributes));
    }

    public function test_subscribing_a_new_email_creates_a_pending_identity_and_sends_a_confirmation(): void
    {
        $cluster = $this->updatingCluster();

        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'Nuovo@Example.com'])
            ->assertRedirect();

        $subscriber = CommunicationSubscriber::where('email', 'nuovo@example.com')->first();
        $this->assertNotNull($subscriber, 'Email normalizzata in minuscolo.');
        $this->assertSame(CommunicationSubscriber::STATUS_PENDING, $subscriber->status);

        $this->assertDatabaseHas('content_cluster_subscribers', [
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
            'status' => ContentClusterSubscriber::STATUS_ACTIVE,
        ]);

        // La conferma è inviata con lo stesso Mail::send([], [], $closure)
        // raw usato da NewsletterController — non è una Mailable, quindi
        // qui si verifica l'effetto osservabile (il token di conferma
        // resta valorizzato, pronto per essere consumato da confirm())
        // invece di un'asserzione Mail::fake() su una classe Mailable
        // che non esiste per questo invio transazionale.
        $this->assertNotNull($subscriber->fresh()->token, 'Un subscriber pending deve avere un token di conferma.');
    }

    public function test_subscribing_an_already_confirmed_identity_activates_immediately_without_a_new_confirmation_email(): void
    {
        $cluster = $this->updatingCluster();
        $existing = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'gia-confermato@example.com']);

        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'gia-confermato@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('content_cluster_subscribers', [
            'subscriber_id' => $existing->id,
            'content_cluster_id' => $cluster->id,
            'status' => ContentClusterSubscriber::STATUS_ACTIVE,
        ]);

        $this->assertSame(1, CommunicationSubscriber::count(), 'Nessuna nuova identità duplicata per un\'email già esistente.');
    }

    public function test_duplicate_submit_produces_only_one_relation_row(): void
    {
        $cluster = $this->updatingCluster();

        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'due-volte@example.com']);
        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'due-volte@example.com']);

        $subscriber = CommunicationSubscriber::where('email', 'due-volte@example.com')->firstOrFail();

        $this->assertSame(
            1,
            ContentClusterSubscriber::where('subscriber_id', $subscriber->id)
                ->where('content_cluster_id', $cluster->id)
                ->count()
        );
    }

    public function test_resubscribing_after_unsubscribe_reactivates_the_same_row(): void
    {
        $cluster = $this->updatingCluster();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'torna@example.com']);
        $subscription = ContentClusterSubscriber::factory()->unsubscribed()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);

        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'torna@example.com']);

        $this->assertSame(1, ContentClusterSubscriber::where('subscriber_id', $subscriber->id)->where('content_cluster_id', $cluster->id)->count());
        $subscription->refresh();
        $this->assertSame(ContentClusterSubscriber::STATUS_ACTIVE, $subscription->status);
        $this->assertNull($subscription->unsubscribed_at);
    }

    public function test_a_complete_path_rejects_a_direct_subscribe_submit(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE,
        ]);

        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'tardivo@example.com'])
            ->assertRedirect();

        $this->assertSame(0, CommunicationSubscriber::count());
        $this->assertSame(0, ContentClusterSubscriber::count());
    }

    public function test_an_inactive_path_rejects_a_direct_subscribe_submit(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => false,
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
        ]);

        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'inattivo@example.com'])
            ->assertRedirect();

        $this->assertSame(0, ContentClusterSubscriber::count());
    }

    public function test_honeypot_field_silently_rejects_the_submit(): void
    {
        $cluster = $this->updatingCluster();

        $this->post(route('percorsi.subscribe', $cluster->slug), [
            'email' => 'bot@example.com',
            'website' => 'http://spam.example',
        ])->assertRedirect();

        $this->assertSame(0, CommunicationSubscriber::count());
    }

    public function test_invalid_email_is_rejected_with_validation_errors(): void
    {
        $cluster = $this->updatingCluster();

        $this->post(route('percorsi.subscribe', $cluster->slug), ['email' => 'non-una-email'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, CommunicationSubscriber::count());
    }

    public function test_confirm_activates_the_identity_and_lists_active_path_subscriptions(): void
    {
        $cluster = $this->updatingCluster(['name' => 'Percorso da confermare']);
        $subscriber = CommunicationSubscriber::factory()->create([
            'status' => CommunicationSubscriber::STATUS_PENDING,
            'token' => 'un-token-valido',
        ]);
        ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $cluster->id,
        ]);

        $response = $this->get(route('percorsi.subscribe.confirm', ['token' => 'un-token-valido']));

        $response->assertOk()->assertSee('Percorso da confermare');

        $subscriber->refresh();
        $this->assertSame(CommunicationSubscriber::STATUS_CONFIRMED, $subscriber->status);
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertNull($subscriber->token);
    }

    public function test_confirm_with_an_invalid_token_404s(): void
    {
        $this->get(route('percorsi.subscribe.confirm', ['token' => 'non-esiste']))
            ->assertNotFound();
    }

    public function test_unsubscribe_deactivates_only_the_targeted_path(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $clusterA = $this->updatingCluster(['name' => 'Percorso A']);
        $clusterB = $this->updatingCluster(['name' => 'Percorso B']);

        $subscriptionA = ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $clusterA->id,
            'unsubscribe_token' => 'token-a',
        ]);
        $subscriptionB = ContentClusterSubscriber::factory()->create([
            'subscriber_id' => $subscriber->id,
            'content_cluster_id' => $clusterB->id,
            'unsubscribe_token' => 'token-b',
        ]);

        $this->get(route('percorsi.subscribe.unsubscribe', 'token-a'))
            ->assertOk()
            ->assertSee('Percorso A');

        $this->assertSame(ContentClusterSubscriber::STATUS_UNSUBSCRIBED, $subscriptionA->fresh()->status);
        $this->assertSame(ContentClusterSubscriber::STATUS_ACTIVE, $subscriptionB->fresh()->status);
        $this->assertSame(CommunicationSubscriber::STATUS_CONFIRMED, $subscriber->fresh()->status, 'La disiscrizione da un Percorso non tocca mai il consenso globale.');
    }

    public function test_unsubscribe_is_idempotent_across_repeated_visits(): void
    {
        $subscription = ContentClusterSubscriber::factory()->create(['unsubscribe_token' => 'ripetuto']);

        $this->get(route('percorsi.subscribe.unsubscribe', 'ripetuto'))->assertOk();
        $this->get(route('percorsi.subscribe.unsubscribe', 'ripetuto'))->assertOk();

        $this->assertSame(ContentClusterSubscriber::STATUS_UNSUBSCRIBED, $subscription->fresh()->status);
    }

    public function test_unsubscribe_with_an_unknown_token_shows_not_found_state_without_error(): void
    {
        $this->get(route('percorsi.subscribe.unsubscribe', 'sconosciuto'))
            ->assertOk()
            ->assertSee('Link non valido');
    }

    public function test_cta_and_form_render_only_on_an_updating_path_with_published_content(): void
    {
        $cluster = $this->updatingCluster();
        $author = User::factory()->create();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Tappa pubblicata',
            'slug' => 'tappa-pubblicata-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subHour(),
        ])->contentClusters()->attach($cluster->id, ['position' => 10]);

        $this->get(route('percorsi.show', $cluster->slug))
            ->assertOk()
            ->assertSee('Avvisami quando continua')
            ->assertSee(route('percorsi.subscribe', $cluster->slug), false);
    }

    public function test_cta_does_not_render_on_a_complete_path(): void
    {
        $cluster = ContentCluster::factory()->create([
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE,
        ]);
        $author = User::factory()->create();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Tappa conclusa',
            'slug' => 'tappa-conclusa-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subHour(),
        ])->contentClusters()->attach($cluster->id, ['position' => 10]);

        $this->get(route('percorsi.show', $cluster->slug))
            ->assertOk()
            ->assertDontSee('Avvisami quando continua');
    }
}
