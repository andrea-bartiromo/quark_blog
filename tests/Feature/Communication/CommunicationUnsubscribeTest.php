<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_confirm_page_never_mutates_subscriber_status(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $response = $this->get(route('comunicazione.disiscrizione.conferma', $subscriber->unsubscribe_token));

        $response->assertOk();
        $response->assertSee($subscriber->email);
        $this->assertSame(CommunicationSubscriber::STATUS_CONFIRMED, $subscriber->fresh()->status);
    }

    public function test_get_confirm_page_repeated_many_times_never_mutates_anything(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->get(route('comunicazione.disiscrizione.conferma', $subscriber->unsubscribe_token))->assertOk();
        }

        $this->assertSame(CommunicationSubscriber::STATUS_CONFIRMED, $subscriber->fresh()->status);
        $this->assertNull($subscriber->fresh()->unsubscribed_at);
    }

    public function test_post_unsubscribes_a_confirmed_subscriber(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $response = $this->post(route('comunicazione.disiscrizione.submit', $subscriber->unsubscribe_token));

        $response->assertOk();
        $fresh = $subscriber->fresh();
        $this->assertSame(CommunicationSubscriber::STATUS_UNSUBSCRIBED, $fresh->status);
        $this->assertNotNull($fresh->unsubscribed_at);
    }

    public function test_post_is_idempotent_across_double_click_or_refresh(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $this->post(route('comunicazione.disiscrizione.submit', $subscriber->unsubscribe_token))->assertOk();
        $firstUnsubscribedAt = $subscriber->fresh()->unsubscribed_at;

        $this->post(route('comunicazione.disiscrizione.submit', $subscriber->unsubscribe_token))->assertOk();
        $secondUnsubscribedAt = $subscriber->fresh()->unsubscribed_at;

        $this->assertSame(CommunicationSubscriber::STATUS_UNSUBSCRIBED, $subscriber->fresh()->status);
        // Il secondo POST non deve "ri-timbrare" unsubscribed_at: la UPDATE
        // è condizionata su status != unsubscribed, quindi un secondo giro
        // non tocca affatto la riga.
        $this->assertEquals($firstUnsubscribedAt, $secondUnsubscribedAt);
    }

    public function test_pending_subscriber_can_also_unsubscribe(): void
    {
        $subscriber = CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_PENDING]);

        $response = $this->post(route('comunicazione.disiscrizione.submit', $subscriber->unsubscribe_token));

        $response->assertOk();
        $this->assertSame(CommunicationSubscriber::STATUS_UNSUBSCRIBED, $subscriber->fresh()->status);
    }

    public function test_invalid_token_returns_a_generic_not_found_page_without_leaking_which_part_is_wrong(): void
    {
        $response = $this->get(route('comunicazione.disiscrizione.conferma', 'token-che-non-esiste-affatto'));

        $response->assertNotFound();
        $this->assertDoesNotMatchRegularExpression('/[\w.+-]+@[\w-]+\.[\w.-]+/', $response->getContent());
    }

    public function test_empty_token_returns_not_found(): void
    {
        // Nessuna route esplicita per il token vuoto (il segmento è
        // obbligatorio), ma un token di soli spazi url-encoded deve
        // comunque risolvere in not-found, non in un errore 500.
        $response = $this->get('/comunicazione/disiscrizione/%20');

        $response->assertNotFound();
    }

    public function test_post_with_invalid_token_returns_not_found_without_mutating_anything(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $response = $this->post(route('comunicazione.disiscrizione.submit', 'token-inventato-'.$subscriber->id));

        $response->assertNotFound();
        $this->assertSame(CommunicationSubscriber::STATUS_CONFIRMED, $subscriber->fresh()->status);
    }

    public function test_already_unsubscribed_confirm_page_shows_idempotent_message_not_an_error(): void
    {
        $subscriber = CommunicationSubscriber::factory()->create([
            'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now()->subDay(),
        ]);

        $response = $this->get(route('comunicazione.disiscrizione.conferma', $subscriber->unsubscribe_token));

        $response->assertOk();
        $response->assertSee('già disiscritto');
    }

    public function test_no_authentication_is_required(): void
    {
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $this->assertGuest();
        $this->get(route('comunicazione.disiscrizione.conferma', $subscriber->unsubscribe_token))->assertOk();
        $this->post(route('comunicazione.disiscrizione.submit', $subscriber->unsubscribe_token))->assertOk();
    }

    public function test_unsubscribing_one_subscriber_never_affects_another(): void
    {
        $target = CommunicationSubscriber::factory()->confirmed()->create();
        $other = CommunicationSubscriber::factory()->confirmed()->create();

        $this->post(route('comunicazione.disiscrizione.submit', $target->unsubscribe_token));

        $this->assertSame(CommunicationSubscriber::STATUS_UNSUBSCRIBED, $target->fresh()->status);
        $this->assertSame(CommunicationSubscriber::STATUS_CONFIRMED, $other->fresh()->status);
    }
}
