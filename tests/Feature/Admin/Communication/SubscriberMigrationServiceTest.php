<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationSubscriber;
use App\Models\Newsletter;
use App\Services\Communication\SubscriberMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberMigrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_confirmed_newsletter_subscriber_is_copied_with_confirmed_status(): void
    {
        Newsletter::create(['email' => 'confermato@example.com', 'confirmed' => true, 'token' => null, 'unsubscribe_token' => 'unsub-token-123']);

        (new SubscriberMigrationService)->migrate();

        $this->assertDatabaseHas('comm_subscribers', [
            'email' => 'confermato@example.com',
            'status' => CommunicationSubscriber::STATUS_CONFIRMED,
            'unsubscribe_token' => 'unsub-token-123',
        ]);
    }

    public function test_an_unconfirmed_newsletter_subscriber_is_copied_with_pending_status(): void
    {
        Newsletter::create(['email' => 'in-attesa@example.com', 'confirmed' => false, 'token' => 'conferma-token-abc', 'unsubscribe_token' => 'unsub-token-456']);

        (new SubscriberMigrationService)->migrate();

        $this->assertDatabaseHas('comm_subscribers', [
            'email' => 'in-attesa@example.com',
            'status' => CommunicationSubscriber::STATUS_PENDING,
            'token' => 'conferma-token-abc',
        ]);
    }

    public function test_token_and_unsubscribe_token_are_preserved_exactly(): void
    {
        Newsletter::create(['email' => 'token@example.com', 'confirmed' => false, 'token' => 'tok-xyz', 'unsubscribe_token' => 'unsub-xyz']);

        (new SubscriberMigrationService)->migrate();

        $copied = CommunicationSubscriber::where('email', 'token@example.com')->first();

        $this->assertSame('tok-xyz', $copied->token);
        $this->assertSame('unsub-xyz', $copied->unsubscribe_token);
    }

    public function test_original_newsletter_table_is_never_modified_or_deleted(): void
    {
        Newsletter::create(['email' => 'intatto@example.com', 'confirmed' => true, 'unsubscribe_token' => 'unsub-intatto']);

        (new SubscriberMigrationService)->migrate();

        $this->assertDatabaseCount('newsletter', 1);
        $this->assertDatabaseHas('newsletter', ['email' => 'intatto@example.com', 'confirmed' => true]);
    }

    public function test_running_the_migration_twice_does_not_duplicate_subscribers(): void
    {
        Newsletter::create(['email' => 'ripetuto@example.com', 'confirmed' => true, 'unsubscribe_token' => 'unsub-ripetuto']);

        $first = (new SubscriberMigrationService)->migrate();
        $second = (new SubscriberMigrationService)->migrate();

        $this->assertSame(1, $first['copied']);
        $this->assertSame(0, $first['already_present']);
        $this->assertSame(0, $second['copied']);
        $this->assertSame(1, $second['already_present']);
        $this->assertDatabaseCount('comm_subscribers', 1);
    }

    public function test_report_counts_copied_already_present_and_errors(): void
    {
        Newsletter::create(['email' => 'nuovo@example.com', 'confirmed' => true, 'unsubscribe_token' => 'unsub-nuovo']);
        CommunicationSubscriber::factory()->create(['email' => 'gia-presente@example.com']);
        Newsletter::create(['email' => 'gia-presente@example.com', 'confirmed' => false, 'unsubscribe_token' => 'unsub-gia-presente']);

        $result = (new SubscriberMigrationService)->migrate();

        $this->assertSame(1, $result['copied']);
        $this->assertSame(1, $result['already_present']);
        $this->assertSame([], $result['errors']);
    }

    public function test_dry_run_reports_counts_without_writing_any_row(): void
    {
        Newsletter::create(['email' => 'simulato@example.com', 'confirmed' => true, 'unsubscribe_token' => 'unsub-simulato']);

        $result = (new SubscriberMigrationService)->migrate(dryRun: true);

        $this->assertSame(1, $result['copied']);
        $this->assertDatabaseCount('comm_subscribers', 0);
    }

    public function test_artisan_command_runs_the_migration_and_reports_counts(): void
    {
        Newsletter::create(['email' => 'via-comando@example.com', 'confirmed' => true, 'unsubscribe_token' => 'unsub-via-comando']);

        $this->artisan('communication:migrate-subscribers')
            ->expectsOutputToContain('Copiati: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('comm_subscribers', ['email' => 'via-comando@example.com']);
    }
}
