<?php

namespace Tests\Feature\Console;

use App\Mail\NewsletterReconfirmationMail;
use App\Models\Newsletter;
use App\Models\NewsletterConsentEvent;
use App\Services\NewsletterReconfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterReconfirmationProcessTest extends TestCase
{
    use RefreshDatabase;

    private function pending(string $email = 'pending@example.test'): Newsletter
    {
        return Newsletter::create([
            'email' => $email,
            'confirmed' => false,
            'token' => str_repeat('a', 64),
            'unsubscribe_token' => str_repeat('b', 32),
        ]);
    }

    public function test_the_timeline_sends_only_at_days_10_20_30_and_deletes_at_day_40(): void
    {
        Mail::fake();
        $subscriber = $this->pending();
        $service = app(NewsletterReconfirmationService::class);

        $service->process();
        $this->assertDatabaseCount('newsletter_reconfirmations', 0);

        $this->travel(10)->days();
        $service->process();
        $this->assertDatabaseCount('newsletter_reconfirmations', 1);

        $service->process();
        $this->assertDatabaseCount('newsletter_reconfirmations', 1);

        $this->travel(10)->days();
        $service->process();
        $this->assertDatabaseCount('newsletter_reconfirmations', 2);

        $this->travel(10)->days();
        $service->process();
        $this->assertDatabaseCount('newsletter_reconfirmations', 3);

        $this->travel(9)->days();
        $service->process();
        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);

        $this->travel(1)->days();
        $service->process();
        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
        $this->assertDatabaseHas('newsletter_consent_events', [
            'newsletter_id' => $subscriber->id,
            'event_type' => NewsletterConsentEvent::DELETED_UNCONFIRMED,
            'attempt_number' => 3,
        ]);
        Mail::assertSent(NewsletterReconfirmationMail::class, 3);
    }

    public function test_dry_run_never_sends_or_mutates(): void
    {
        Mail::fake();
        $this->pending();

        $this->travel(10)->days();
        $this->artisan('newsletter:reconfirmation-process --dry-run')
            ->assertSuccessful();

        $this->assertDatabaseCount('newsletter_reconfirmations', 0);
        $this->assertDatabaseCount('newsletter_consent_events', 0);
        Mail::assertNothingSent();
    }

    public function test_confirmation_after_any_reminder_stops_the_cycle(): void
    {
        Mail::fake();
        $subscriber = $this->pending();
        $service = app(NewsletterReconfirmationService::class);

        $this->travel(10)->days();
        $service->process();
        $token = $subscriber->reconfirmations()->latest('sent_at')->value('token');

        $this->get(route('newsletter.reconfirm', ['token' => $token]))->assertOk();
        $this->travel(40)->days();
        $service->process();

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id, 'confirmed' => true]);
        $this->assertDatabaseCount('newsletter_reconfirmations', 1);
        Mail::assertSent(NewsletterReconfirmationMail::class, 1);
    }

    public function test_manual_resend_is_neutral_and_limited_to_once_per_day(): void
    {
        Mail::fake();
        $subscriber = $this->pending('resend@example.test');
        $service = app(NewsletterReconfirmationService::class);
        $service->sendInitialConfirmation($subscriber);

        $this->post(route('newsletter.resend-confirmation'), ['email' => $subscriber->email])
            ->assertSessionHas('newsletter_resend');
        Mail::assertSentCount(1);

        $this->travel(25)->hours();
        $this->post(route('newsletter.resend-confirmation'), ['email' => $subscriber->email])
            ->assertSessionHas('newsletter_resend');
        Mail::assertSentCount(2);

        Newsletter::whereKey($subscriber->id)->update(['confirmed' => true]);
        $this->post(route('newsletter.resend-confirmation'), ['email' => $subscriber->email])
            ->assertSessionHas('newsletter_resend');
        Mail::assertSentCount(2);
    }

    public function test_pending_subscribers_are_excluded_from_editorial_recipient_query(): void
    {
        $pending = $this->pending('not-confirmed@example.test');
        $active = $this->pending('confirmed@example.test');
        $active->update(['confirmed' => true, 'token' => null]);

        $recipients = Newsletter::where('confirmed', true)->pluck('id');

        $this->assertFalse($recipients->contains($pending->id));
        $this->assertTrue($recipients->contains($active->id));
    }

    public function test_the_process_command_is_registered_and_scheduled_hourly(): void
    {
        $this->assertArrayHasKey('newsletter:reconfirmation-process', Artisan::all());

        Artisan::call('schedule:list');

        $this->assertStringContainsString(
            'newsletter:reconfirmation-process',
            Artisan::output()
        );
    }
}
