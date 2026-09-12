<?php

namespace Tests\Feature\Console;

use App\Models\Newsletter;
use App\Models\NewsletterConsentEvent;
use App\Models\NewsletterReconfirmation;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CleanupExpiredNewsletterPendingTest extends TestCase
{
    use RefreshDatabase;

    private function pendingWithThreeExpiredReminders(string $email): Newsletter
    {
        $subscriber = Newsletter::subscribe($email);

        foreach ([30, 20, 10] as $daysAgo) {
            NewsletterReconfirmation::create([
                'newsletter_id' => $subscriber->id,
                'token' => Str::random(64),
                'sent_at' => now()->subDays($daysAgo),
                'expires_at' => now()->subDays(max(1, $daysAgo - 1)),
            ]);
        }

        return $subscriber;
    }

    public function test_cleanup_never_deletes_after_only_one_or_two_reminders(): void
    {
        $subscriber = Newsletter::subscribe('too-early@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(20),
            'expires_at' => now()->subDays(10),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertSuccessful();

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_cleanup_deletes_only_after_ten_days_from_the_third_reminder(): void
    {
        $subscriber = $this->pendingWithThreeExpiredReminders('complete-cycle@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup')->assertSuccessful();

        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
        $this->assertDatabaseHas('newsletter_consent_events', [
            'newsletter_id' => $subscriber->id,
            'event_type' => NewsletterConsentEvent::DELETED_UNCONFIRMED,
            'attempt_number' => 3,
        ]);
    }

    public function test_dry_run_does_not_delete_a_complete_cycle(): void
    {
        $subscriber = $this->pendingWithThreeExpiredReminders('dry-run-complete@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup --dry-run')->assertSuccessful();

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_kill_switch_skips_cleanup_fail_closed(): void
    {
        config(['newsletter.reconfirmation.cleanup_enabled' => false]);
        $subscriber = $this->pendingWithThreeExpiredReminders('disabled@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup')->assertSuccessful();

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_legacy_command_remains_registered_but_is_not_scheduled_separately(): void
    {
        $this->assertArrayHasKey('newsletter:reconfirmation-cleanup', \Illuminate\Support\Facades\Artisan::all());

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) ($event->command ?? ''), 'newsletter:reconfirmation-process'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
    }
}
