<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDryRunService;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use ReflectionMethod;
use Tests\TestCase;

class CampaignDryRunTest extends TestCase
{
    use RefreshDatabase;

    private function dryRunService(): CampaignDryRunService
    {
        return app(CampaignDryRunService::class);
    }

    private function draftCampaignWithQueuedRecipients(int $count = 1): CommunicationCampaign
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
        ]);

        for ($i = 0; $i < $count; $i++) {
            CommunicationSend::create([
                'campaign_id' => $campaign->id,
                'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
                'status' => CommunicationSend::STATUS_QUEUED,
            ]);
        }

        return $campaign;
    }

    public function test_the_run_method_only_accepts_a_recording_email_provider_by_type(): void
    {
        // Garanzia a livello di firma, non solo di convenzione: un
        // dry-run con un provider diverso da un fake non deve nemmeno
        // poter compilare. Verifica via reflection sul type hint reale.
        $method = new ReflectionMethod(CampaignDryRunService::class, 'run');
        $providerParam = $method->getParameters()[1];

        $this->assertSame(RecordingEmailProvider::class, $providerParam->getType()?->getName());
    }

    public function test_dry_run_reports_the_six_canonical_counters(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(3);

        $report = $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        $this->assertSame([
            'eligible' => 3,
            'skipped' => 0,
            'rendered' => 3,
            'accepted' => 3,
            'transient_failed' => 0,
            'permanent_failed' => 0,
        ], $report->toArray());
    }

    public function test_dry_run_covers_the_full_failure_distribution(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(0);

        $accepted = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $transient = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $permanent = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        // Un iscritto disiscritto DOPO lo snapshot fallisce in
        // revalidazione (mai "skipped" — quella categoria è riservata a
        // corse di claim perse, vedi SendProcessingOutcome) e ricade
        // nel ramo "default" di CampaignRunReport::withOutcome(),
        // quindi conta come renderizzato + fallito definitivo.
        $ineligibleSubscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $revalidationFailure = CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $ineligibleSubscriber->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $ineligibleSubscriber->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED]);

        $provider = new RecordingEmailProvider;
        $provider->resolveUsing(function ($message) use ($accepted, $transient, $permanent) {
            $subscriberId = $message->recipientSubscriberId;

            return match ($subscriberId) {
                $accepted->subscriber_id => new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'ok', idempotencyKey: $message->idempotencyKey),
                $transient->subscriber_id => new DeliveryResult(status: DeliveryResult::STATUS_TRANSIENT_FAILURE, providerMessageId: null, idempotencyKey: $message->idempotencyKey, reason: 'timeout'),
                $permanent->subscriber_id => new DeliveryResult(status: DeliveryResult::STATUS_PERMANENT_FAILURE, providerMessageId: null, idempotencyKey: $message->idempotencyKey, reason: 'rejected'),
                default => new DeliveryResult(status: DeliveryResult::STATUS_ACCEPTED, providerMessageId: 'ok', idempotencyKey: $message->idempotencyKey),
            };
        });

        $report = $this->dryRunService()->run($campaign, $provider);

        $this->assertSame(4, $report->eligible);
        $this->assertSame(0, $report->skipped);
        $this->assertSame(1, $report->accepted);
        $this->assertSame(1, $report->transientFailed);
        // Il fallimento esplicito del provider (rejected) + il fallimento
        // di revalidazione (iscritto non più confermato) sommano a due.
        $this->assertSame(2, $report->permanentFailed);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $revalidationFailure->fresh()->status);
    }

    public function test_dry_run_never_persists_any_mutation_to_campaign_or_sends(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(2);
        $send = CommunicationSend::where('campaign_id', $campaign->id)->first();

        $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->sending_started_at);
        $this->assertNull($campaign->fresh()->completed_at);
        $this->assertSame(CommunicationSend::STATUS_QUEUED, $send->fresh()->status);
        $this->assertSame(0, $send->fresh()->attempts);
        $this->assertNull($send->fresh()->sent_at);
    }

    public function test_the_campaign_passed_by_reference_reflects_the_rolled_back_state_not_the_in_flight_one(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(1);

        $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        // Durante l'esecuzione l'istanza sarebbe temporaneamente
        // 'completed' (stessa strada di runCampaign()) — dopo il
        // rollback deve tornare a riflettere lo stato reale su disco,
        // mai mentire al chiamante.
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->status);
    }

    public function test_dry_run_is_repeatable_with_identical_results(): void
    {
        $campaign = $this->draftCampaignWithQueuedRecipients(5);

        $first = $this->dryRunService()->run($campaign, new RecordingEmailProvider);
        $second = $this->dryRunService()->run($campaign->fresh(), new RecordingEmailProvider);

        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_dry_run_never_touches_mail_notification_or_queue(): void
    {
        Mail::fake();
        Notification::fake();
        Bus::fake();

        $campaign = $this->draftCampaignWithQueuedRecipients(2);

        $this->dryRunService()->run($campaign, new RecordingEmailProvider);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    public function test_dry_run_source_never_references_a_real_provider_or_transport(): void
    {
        $source = file_get_contents(app_path('Services/Communication/CampaignDryRunService.php'));

        foreach (['Mail::', 'Notification::', 'Bus::', 'Http::', 'curl_', 'fsockopen', 'Swift_', 'Symfony\\Component\\Mailer'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }
}
