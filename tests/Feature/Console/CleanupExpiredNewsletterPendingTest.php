<?php

namespace Tests\Feature\Console;

use App\Models\Newsletter;
use App\Models\NewsletterReconfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
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

    /**
     * Prompt 101-105 (150-prompt program, revisione critica della PR
     * #533 di questa stessa sessione): questo comando gira ogni giorno
     * senza supervisione contro una tabella senza soft-delete —
     * --dry-run deve riusare la STESSA query di eleggibilita' della
     * cancellazione reale (non una copia che potrebbe divergere) e non
     * deve mai modificare nulla.
     */
    public function test_dry_run_reports_eligible_subscribers_without_deleting_anything(): void
    {
        $expired = Newsletter::subscribe('dry-run-expired@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $expired->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);
        $neverSollicited = Newsletter::subscribe('dry-run-never-sollicited@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup', ['--dry-run' => true])
            ->expectsOutputToContain((string) $expired->id)
            ->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $expired->id]);
        $this->assertDatabaseHas('newsletter', ['id' => $neverSollicited->id]);
    }

    public function test_dry_run_reports_nothing_to_remove_when_no_one_is_eligible(): void
    {
        Newsletter::subscribe('dry-run-nobody-eligible@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('nessun pendente scaduto da rimuovere')
            ->assertExitCode(0);
    }

    public function test_a_real_deletion_run_logs_the_removed_subscriber_ids_but_never_the_email(): void
    {
        Log::spy();

        $subscriber = Newsletter::subscribe('logged-deletion@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($subscriber) {
                return str_contains($message, 'Rimozione iscritti newsletter pendenti scaduti')
                    && $context['subscriber_ids'] === [$subscriber->id]
                    && $context['count'] === 1
                    && ! str_contains(json_encode($context), 'logged-deletion@example.com');
            });
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
}
