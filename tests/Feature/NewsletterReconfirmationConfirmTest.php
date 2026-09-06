<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recupero prudente degli iscritti pendenti — consumo pubblico del token
 * di riconferma (NewsletterController::reconfirm()). Flusso interamente
 * distinto dal double opt-in originale (NewsletterController::confirm()),
 * mai toccato da questi test — vedi NewsletterConfirmationTokenTest.
 */
class NewsletterReconfirmationConfirmTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function sendReconfirmation(Newsletter $subscriber): string
    {
        Mail::fake();
        $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        return $subscriber->reconfirmations()->latest('sent_at')->first()->token;
    }

    public function test_a_valid_token_confirms_the_subscriber(): void
    {
        $subscriber = Newsletter::subscribe('reconfirm-valid@example.com');
        $token = $this->sendReconfirmation($subscriber);

        $response = $this->get(route('newsletter.reconfirm', ['token' => $token]));

        $response->assertOk()->assertViewIs('newsletter-reconfirmed')->assertSee('Iscrizione confermata!');

        $subscriber->refresh();
        $this->assertTrue($subscriber->confirmed);
        $this->assertNull($subscriber->token);
    }

    public function test_the_reconfirmation_record_is_marked_confirmed(): void
    {
        $subscriber = Newsletter::subscribe('reconfirm-record@example.com');
        $token = $this->sendReconfirmation($subscriber);

        $this->get(route('newsletter.reconfirm', ['token' => $token]));

        $this->assertDatabaseHas('newsletter_reconfirmations', [
            'token' => $token,
        ]);
        $record = $subscriber->reconfirmations()->where('token', $token)->first();
        $this->assertNotNull($record->confirmed_at);
    }

    public function test_an_expired_token_does_not_confirm(): void
    {
        $subscriber = Newsletter::subscribe('reconfirm-expired@example.com');
        $token = $this->sendReconfirmation($subscriber);

        $this->travel((int) config('newsletter.reconfirmation.expires_after_days') + 1)->days();

        $response = $this->get(route('newsletter.reconfirm', ['token' => $token]));

        $response->assertOk()->assertDontSee('Iscrizione confermata!');

        $subscriber->refresh();
        $this->assertFalse($subscriber->confirmed);
    }

    public function test_an_unknown_token_does_not_confirm_anything(): void
    {
        $subscriber = Newsletter::subscribe('reconfirm-unknown@example.com');

        $response = $this->get(route('newsletter.reconfirm', ['token' => 'this-token-does-not-exist']));

        $response->assertOk()->assertDontSee('Iscrizione confermata!');

        $subscriber->refresh();
        $this->assertFalse($subscriber->confirmed);
    }

    public function test_a_missing_token_does_not_confirm_anything(): void
    {
        $response = $this->get(route('newsletter.reconfirm'));

        $response->assertOk()->assertDontSee('Iscrizione confermata!');
    }

    public function test_confirming_with_one_subscribers_token_does_not_affect_another(): void
    {
        $target = Newsletter::subscribe('reconfirm-target@example.com');
        $other = Newsletter::subscribe('reconfirm-other@example.com');

        $targetToken = $this->sendReconfirmation($target);
        $this->sendReconfirmation($other);

        $this->get(route('newsletter.reconfirm', ['token' => $targetToken]));

        $target->refresh();
        $other->refresh();

        $this->assertTrue($target->confirmed);
        $this->assertFalse($other->confirmed);
    }
}
