<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NewsletterUnsubscribeTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_adds_the_unsubscribe_token_column(): void
    {
        $this->assertTrue(Schema::hasColumn('newsletter', 'unsubscribe_token'));
    }

    public function test_model_saves_and_rereads_the_token(): void
    {
        $subscriber = Newsletter::create([
            'email' => 'model-roundtrip@example.com',
            'confirmed' => true,
            'unsubscribe_token' => 'a-known-32-char-token-value-xx',
        ]);

        $fresh = Newsletter::find($subscriber->id);

        $this->assertSame('a-known-32-char-token-value-xx', $fresh->unsubscribe_token);
    }

    public function test_new_subscription_receives_a_valid_token(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('new-subscriber@example.com');

        $this->assertNotNull($subscriber->unsubscribe_token);
        $this->assertSame(32, strlen($subscriber->unsubscribe_token));
    }

    public function test_different_subscribers_receive_different_tokens(): void
    {
        Mail::fake();

        $a = Newsletter::subscribe('subscriber-a@example.com');
        $b = Newsletter::subscribe('subscriber-b@example.com');

        $this->assertNotSame($a->unsubscribe_token, $b->unsubscribe_token);
    }

    public function test_unique_constraint_prevents_duplicate_tokens(): void
    {
        Newsletter::create([
            'email' => 'first@example.com',
            'confirmed' => false,
            'unsubscribe_token' => 'duplicate-token-value-000000000',
        ]);

        $this->expectException(QueryException::class);

        DB::table('newsletter')->insert([
            'email' => 'second@example.com',
            'confirmed' => false,
            'unsubscribe_token' => 'duplicate-token-value-000000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_legacy_subscriber_without_a_token_is_not_removed_by_a_missing_token_request(): void
    {
        // Simula un iscritto legacy come quelli che lo script manuale
        // fix_newsletter.php doveva sanare: unsubscribe_token nullo.
        DB::table('newsletter')->insert([
            'email' => 'legacy@example.com',
            'confirmed' => true,
            'unsubscribe_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Nessun parametro token in query string: senza la guardia nel
        // controller, Eloquent tratterebbe where('unsubscribe_token', null)
        // come whereNull() e cancellerebbe questo iscritto legacy.
        $response = $this->get(route('newsletter.unsubscribe'));

        $response->assertOk();
        $this->assertDatabaseHas('newsletter', ['email' => 'legacy@example.com']);
    }

    public function test_unsubscribe_with_valid_token_removes_the_correct_subscriber(): void
    {
        Mail::fake();

        $target = Newsletter::subscribe('target@example.com');
        $other = Newsletter::subscribe('other@example.com');

        $response = $this->get(route('newsletter.unsubscribe', ['token' => $target->unsubscribe_token]));

        $response->assertOk();
        $this->assertDatabaseMissing('newsletter', ['email' => 'target@example.com']);
        $this->assertDatabaseHas('newsletter', ['email' => 'other@example.com', 'id' => $other->id]);
    }

    public function test_unsubscribe_with_a_nonexistent_token_does_not_error(): void
    {
        $response = $this->get(route('newsletter.unsubscribe', ['token' => 'this-token-does-not-exist']));

        $response->assertOk();
    }

    public function test_newsletter_subscription_still_works_end_to_end(): void
    {
        Mail::fake();

        $response = $this->post(route('newsletter.subscribe'), ['email' => 'subscribe-flow@example.com']);

        $response->assertRedirect('/?newsletter=ok');
        $this->assertDatabaseHas('newsletter', ['email' => 'subscribe-flow@example.com']);

        $subscriber = Newsletter::where('email', 'subscribe-flow@example.com')->first();
        $this->assertNotNull($subscriber->unsubscribe_token);
    }

    public function test_unsubscribe_link_generated_the_same_way_as_the_job_and_email_works(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('link-check@example.com');

        // Stessa chiamata usata da SendNewsletterJob::handle() e da
        // NewsletterController::subscribe() per costruire il link nell'email.
        $unsubscribeUrl = route('newsletter.unsubscribe', ['token' => $subscriber->unsubscribe_token]);

        $this->assertStringContainsString($subscriber->unsubscribe_token, $unsubscribeUrl);

        $response = $this->get($unsubscribeUrl);

        $response->assertOk();
        $this->assertDatabaseMissing('newsletter', ['email' => 'link-check@example.com']);
    }
}
