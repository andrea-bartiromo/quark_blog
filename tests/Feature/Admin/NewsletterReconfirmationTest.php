<?php

namespace Tests\Feature\Admin;

use App\Mail\NewsletterReconfirmationMail;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recupero prudente degli iscritti pendenti — flusso admin di invio del
 * sollecito di riconferma. Copre l'eleggibilità (già confermato,
 * tentativi esauriti, cooldown attivo) e la registrazione dell'invio
 * (NewsletterReconfirmation), non la conferma pubblica (vedi
 * tests/Feature/NewsletterReconfirmationConfirmTest.php).
 */
class NewsletterReconfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_editor_can_send_a_reconfirmation_to_a_pending_subscriber(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('pending@example.com');

        $response = $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $response->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseCount('newsletter_reconfirmations', 1);
        $this->assertDatabaseHas('newsletter_reconfirmations', [
            'newsletter_id' => $subscriber->id,
            'confirmed_at' => null,
        ]);

        Mail::assertSent(NewsletterReconfirmationMail::class, fn ($mail) => $mail->hasTo($subscriber->email));
    }

    public function test_sending_a_reconfirmation_never_marks_the_subscriber_confirmed(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('never-auto-confirmed@example.com');

        $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $subscriber->refresh();

        $this->assertFalse($subscriber->confirmed);
    }

    public function test_reconfirmation_cannot_be_sent_to_an_already_confirmed_subscriber(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('confirmed@example.com');
        $subscriber->update(['confirmed' => true, 'token' => null]);

        $response = $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('newsletter_reconfirmations', 0);
        Mail::assertNothingSent();
    }

    public function test_reconfirmation_respects_the_cooldown_between_sends(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('cooldown@example.com');
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        // Un secondo invio immediato deve essere rifiutato: non è ancora
        // trascorso il cooldown configurato.
        $response = $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('newsletter_reconfirmations', 1);
        Mail::assertSentCount(1);
    }

    public function test_reconfirmation_can_be_sent_again_after_the_cooldown_elapses(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('after-cooldown@example.com');
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $this->travel((int) config('newsletter.reconfirmation.cooldown_hours') + 1)->hours();

        $response = $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('newsletter_reconfirmations', 2);
        Mail::assertSentCount(2);
    }

    public function test_reconfirmation_is_refused_once_the_maximum_number_of_attempts_is_reached(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('max-attempts@example.com');
        $editor = $this->editor();
        $maxAttempts = (int) config('newsletter.reconfirmation.max_attempts');

        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));
            $this->travel((int) config('newsletter.reconfirmation.cooldown_hours') + 1)->hours();
        }

        $this->assertDatabaseCount('newsletter_reconfirmations', $maxAttempts);

        $response = $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('newsletter_reconfirmations', $maxAttempts);
        Mail::assertSentCount($maxAttempts);
    }

    public function test_a_new_send_invalidates_the_previous_unconfirmed_token(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('invalidate-previous@example.com');
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));
        $firstToken = $subscriber->reconfirmations()->first()->token;

        $this->travel((int) config('newsletter.reconfirmation.cooldown_hours') + 1)->hours();
        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $first = $subscriber->reconfirmations()->where('token', $firstToken)->first();
        $this->assertTrue($first->expires_at->isPast(), 'The previous unconfirmed token must be invalidated by the new send.');
    }

    public function test_guest_cannot_send_a_reconfirmation(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('guest-blocked@example.com');

        $response = $this->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('newsletter_reconfirmations', 0);
        Mail::assertNothingSent();
    }
}
