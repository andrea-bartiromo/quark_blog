<?php

namespace Tests\Feature\Console;

use App\Models\Newsletter;
use App\Models\NewsletterReconfirmation;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupExpiredNewsletterPendingTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_deletes_a_pending_subscriber_whose_reconfirmation_token_expired_without_a_reply(): void
    {
        $subscriber = Newsletter::subscribe('expired-pending@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
    }

    public function test_never_deletes_a_pending_subscriber_who_was_never_sent_a_reconfirmation(): void
    {
        $subscriber = Newsletter::subscribe('never-sollicited@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_never_deletes_a_pending_subscriber_whose_reconfirmation_is_still_valid(): void
    {
        $subscriber = Newsletter::subscribe('still-valid@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now(),
            'expires_at' => now()->addDays(6),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_never_deletes_a_confirmed_subscriber_even_with_an_expired_reconfirmation_record(): void
    {
        $subscriber = Newsletter::subscribe('confirmed-with-history@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
            'confirmed_at' => now()->subDays(9),
        ]);
        $subscriber->update(['confirmed' => true, 'token' => null]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id, 'confirmed' => true]);
    }

    public function test_admin_can_trigger_the_same_cleanup_manually(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('manual-cleanup@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.cleanup'));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
    }

    /**
     * Prompt 171-185 (audit e hardening operativo di #533, già in main):
     * fino a questo hardening non esisteva alcun modo di sospendere questa
     * cancellazione automatica giornaliera senza disabilitare l'intero
     * scheduler Laravel. Analogo a NEWSLETTER_SEND_ENABLED per
     * newsletter:send.
     */
    public function test_kill_switch_disabled_skips_the_cleanup_entirely(): void
    {
        config(['newsletter.reconfirmation.cleanup_enabled' => false]);

        $subscriber = Newsletter::subscribe('kill-switch@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    /**
     * Il kill switch copre solo l'esecuzione automatica schedulata:
     * l'editor deve poter continuare a rimuovere pendenti scaduti su
     * richiesta esplicita anche quando la pulizia automatica è sospesa —
     * è già una decisione umana deliberata, non un'attivazione automatica
     * non presidiata.
     */
    public function test_kill_switch_does_not_affect_the_manual_admin_trigger(): void
    {
        config(['newsletter.reconfirmation.cleanup_enabled' => false]);
        Mail::fake();

        $subscriber = Newsletter::subscribe('kill-switch-manual@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.cleanup'));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
    }

    public function test_dry_run_reports_eligible_subscribers_without_deleting_anything(): void
    {
        $subscriber = Newsletter::subscribe('dry-run@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup', ['--dry-run' => true])
            ->expectsOutputToContain((string) $subscriber->id)
            ->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_dry_run_reports_nothing_eligible_when_there_is_nothing_to_remove(): void
    {
        Newsletter::subscribe('dry-run-clean@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('nessun pendente scaduto')
            ->assertExitCode(0);
    }

    /**
     * withoutOverlapping() a livello di scheduler protegge da due run
     * schedulate sovrapposte, ma non da un run manuale (admin o comando)
     * che coincide con quello automatico. Prova la seconda linea di difesa
     * concreta: la stessa cancellazione ripetuta due volte di seguito è un
     * no-op sicuro la seconda volta, non un errore né una doppia
     * cancellazione.
     */
    public function test_running_the_cleanup_twice_in_a_row_is_safe_and_idempotent(): void
    {
        $subscriber = Newsletter::subscribe('idempotent@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);
        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);

        // Seconda esecuzione: nulla di eleggibile è rimasto, deve restare
        // un no-op pulito, mai un errore su una riga già rimossa.
        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);
    }

    public function test_command_is_registered_on_the_scheduler_with_overlap_protection(): void
    {
        $schedule = app(Schedule::class);
        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains((string) ($event->command ?? ''), 'newsletter:reconfirmation-cleanup'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
    }
}
