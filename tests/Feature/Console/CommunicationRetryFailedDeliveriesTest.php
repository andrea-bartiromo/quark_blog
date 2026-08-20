<?php

namespace Tests\Feature\Console;

use App\Jobs\SendPathContinuationNotification;
use App\Models\CommunicationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * S5: CommunicationDeliveryService::retryFailed() esisteva già (riporta
 * failed -> pending) ma non era mai invocata da nulla di raggiungibile
 * dall'operatore — un fallimento era diagnosticabile via query diretta ma
 * non davvero ritentabile. Questo comando chiude quel cerchio riusando
 * quel metodo esistente, mai introducendo un secondo ledger.
 */
class CommunicationRetryFailedDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_failed_deliveries_without_changing_state_by_default(): void
    {
        $delivery = CommunicationDelivery::factory()->failed()->create([
            'notification_type' => 'path_continuation',
        ]);

        Queue::fake();

        $this->artisan('communication:retry-failed-deliveries')
            ->expectsOutputToContain("1 delivery in stato 'failed'")
            ->assertExitCode(0);

        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_release_all_requeues_and_redispatches_the_mapped_job(): void
    {
        $delivery = CommunicationDelivery::factory()->failed()->create([
            'notification_type' => 'path_continuation',
        ]);

        Queue::fake();

        $this->artisan('communication:retry-failed-deliveries', ['--release-all' => true])
            ->expectsOutputToContain('1/1 delivery ri-accodate e ridispacciate.')
            ->assertExitCode(0);

        $this->assertSame(CommunicationDelivery::STATUS_PENDING, $delivery->fresh()->status);
        Queue::assertPushed(SendPathContinuationNotification::class, fn ($job) => $job->deliveryId === $delivery->id);
    }

    public function test_unmapped_notification_type_is_reported_and_skipped_not_guessed(): void
    {
        $delivery = CommunicationDelivery::factory()->failed()->create([
            'notification_type' => 'future_social_launch_notification',
        ]);

        Log::spy();
        Queue::fake();

        $this->artisan('communication:retry-failed-deliveries', ['--release-all' => true])
            ->expectsOutputToContain('non mappato a nessun job — saltata')
            ->assertExitCode(0);

        $this->assertSame(CommunicationDelivery::STATUS_FAILED, $delivery->fresh()->status);
        Queue::assertNothingPushed();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context) => str_contains($message, 'non mappato')
                && $context['delivery_id'] === $delivery->id
                && $context['notification_type'] === 'future_social_launch_notification'
            )
            ->once();
    }

    public function test_type_option_filters_which_failed_deliveries_are_listed(): void
    {
        CommunicationDelivery::factory()->failed()->create(['notification_type' => 'path_continuation']);
        CommunicationDelivery::factory()->failed()->create(['notification_type' => 'other_type']);

        $this->artisan('communication:retry-failed-deliveries', ['--type' => 'other_type'])
            ->expectsOutputToContain("1 delivery in stato 'failed'")
            ->assertExitCode(0);
    }

    public function test_reports_when_there_is_nothing_to_retry(): void
    {
        $this->artisan('communication:retry-failed-deliveries')
            ->expectsOutputToContain('Nessuna delivery in stato "failed" trovata.')
            ->assertExitCode(0);
    }
}
