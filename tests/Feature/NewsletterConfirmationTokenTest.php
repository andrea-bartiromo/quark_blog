<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterConfirmationTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_with_valid_token_confirms_the_correct_subscriber(): void
    {
        Mail::fake();

        $target = Newsletter::subscribe('target-confirm@example.com');
        $other = Newsletter::subscribe('other-confirm@example.com');
        $otherToken = $other->token;

        $response = $this->get(route('newsletter.confirm', ['token' => $target->token]));

        $response
            ->assertOk()
            ->assertViewIs('newsletter-confirmed')
            ->assertSee('Iscrizione confermata!');

        $target->refresh();
        $other->refresh();

        $this->assertTrue($target->confirmed);
        $this->assertNull($target->token);
        $this->assertFalse($other->confirmed);
        $this->assertSame($otherToken, $other->token);
    }

    public function test_confirmation_without_token_does_not_confirm_any_subscriber(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('missing-token@example.com');

        $response = $this->get(route('newsletter.confirm'));

        $response
            ->assertNotFound()
            ->assertDontSee('Iscrizione confermata!');

        $this->assertSubscriberIsUnchanged($subscriber);
    }

    public function test_confirmation_with_empty_token_does_not_confirm_any_subscriber(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('empty-token@example.com');

        $response = $this->get('/newsletter/conferma?token=');

        $response->assertNotFound();
        $this->assertSubscriberIsUnchanged($subscriber);
    }

    public function test_confirmation_with_blank_token_does_not_confirm_any_subscriber(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('blank-token@example.com');

        $response = $this->get('/newsletter/conferma?token=%20%20%20');

        $response->assertNotFound();
        $this->assertSubscriberIsUnchanged($subscriber);
    }

    public function test_confirmation_with_nonexistent_token_does_not_confirm_any_subscriber(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('nonexistent-token@example.com');

        $response = $this->get(route('newsletter.confirm', ['token' => 'this-token-does-not-exist']));

        $response->assertNotFound();
        $this->assertSubscriberIsUnchanged($subscriber);
    }

    public function test_legacy_subscriber_with_null_token_is_not_confirmed_by_missing_token_request(): void
    {
        DB::table('newsletter')->insert([
            'email' => 'legacy-confirm@example.com',
            'confirmed' => false,
            'token' => null,
            'unsubscribe_token' => 'legacy-unsubscribe-token-value',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('newsletter.confirm'));

        $response
            ->assertNotFound()
            ->assertDontSee('Iscrizione confermata!');

        $this->assertDatabaseHas('newsletter', [
            'email' => 'legacy-confirm@example.com',
            'confirmed' => false,
            'token' => null,
        ]);
    }

    private function assertSubscriberIsUnchanged(Newsletter $subscriber): void
    {
        $originalToken = $subscriber->token;

        $subscriber->refresh();

        $this->assertFalse($subscriber->confirmed);
        $this->assertSame($originalToken, $subscriber->token);
    }
}
