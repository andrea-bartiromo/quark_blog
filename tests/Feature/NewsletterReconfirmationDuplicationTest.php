<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Recupero prudente degli iscritti pendenti — garanzie anti-duplicazione:
 * né un doppio invio ravvicinato né un link di conferma visitato più
 * volte devono produrre un secondo effetto reale (una seconda email, una
 * seconda conferma, un doppio conteggio tentativi).
 */
class NewsletterReconfirmationDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_clicking_send_twice_in_a_row_sends_only_one_email(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('duplicate-send@example.com');
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));
        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        Mail::assertSentCount(1);
        $this->assertDatabaseCount('newsletter_reconfirmations', 1);
    }

    public function test_visiting_the_same_confirmation_link_twice_confirms_only_once(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('duplicate-confirm@example.com');
        $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.send', $subscriber));
        $token = $subscriber->reconfirmations()->first()->token;

        $first = $this->get(route('newsletter.reconfirm', ['token' => $token]));
        $second = $this->get(route('newsletter.reconfirm', ['token' => $token]));

        $first->assertSee('Iscrizione confermata!');
        // Il secondo tentativo con lo STESSO token già consumato deve
        // fallire onestamente (link non più valido), non confermare "di
        // nuovo" silenziosamente né sollevare un errore.
        $second->assertOk()->assertDontSee('Iscrizione confermata!');

        $subscriber->refresh();
        $this->assertTrue($subscriber->confirmed);

        $confirmedCount = $subscriber->reconfirmations()->whereNotNull('confirmed_at')->count();
        $this->assertSame(1, $confirmedCount, 'Exactly one reconfirmation record must be marked confirmed, never more.');
    }

    public function test_confirming_does_not_allow_a_later_reconfirmation_attempt(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('no-resend-after-confirm@example.com');
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));
        $token = $subscriber->reconfirmations()->first()->token;
        $this->get(route('newsletter.reconfirm', ['token' => $token]));

        $subscriber->refresh();
        $this->assertTrue($subscriber->confirmed);

        // Un iscritto ormai confermato non deve poter ricevere un altro
        // sollecito — l'idempotenza vale anche dal lato "invio", non solo
        // dal lato "conferma".
        $response = $this->actingAs($editor)
            ->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        $response->assertSessionHas('error');
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('newsletter_reconfirmations', 1);
    }

    public function test_two_reconfirmation_tokens_for_the_same_subscriber_are_never_both_valid(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('single-valid-token@example.com');
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));
        $firstToken = $subscriber->reconfirmations()->first()->token;

        $this->travel((int) config('newsletter.reconfirmation.cooldown_hours') + 1)->hours();
        $this->actingAs($editor)->post(route('admin.newsletter.reconfirmation.send', $subscriber));

        // Il link della PRIMA email, arrivato tardi o riaperto da una
        // casella di posta, non deve più poter confermare dopo che è
        // stato inviato un secondo sollecito.
        $response = $this->get(route('newsletter.reconfirm', ['token' => $firstToken]));

        $response->assertOk()->assertDontSee('Iscrizione confermata!');

        $subscriber->refresh();
        $this->assertFalse($subscriber->confirmed);
    }
}
