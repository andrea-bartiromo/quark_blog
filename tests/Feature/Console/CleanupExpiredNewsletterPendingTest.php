<?php

namespace Tests\Feature\Console;

use App\Models\Newsletter;
use App\Models\NewsletterReconfirmation;
use App\Models\User;
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
}
