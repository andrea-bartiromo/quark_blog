<?php

namespace Tests\Unit\Communication;

use App\Contracts\EmailDeliveryProvider;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignActivityLog;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\CommunicationTestSend;
use App\Models\User;
use App\Services\Communication\CampaignFreezeService;
use App\Services\Communication\CampaignTestSendService;
use App\Services\Communication\DeliveryResult;
use App\Services\Communication\RecordingEmailProvider;
use App\Services\Communication\RenderedCampaignMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CampaignTestSendServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CampaignTestSendService
    {
        return app(CampaignTestSendService::class);
    }

    private function readyFrozenCampaign(): CommunicationCampaign
    {
        $sender = CommunicationSenderProfile::factory()->create();

        $campaign = CommunicationCampaign::factory()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto di prova',
            'content' => ['body' => 'Corpo di prova.'],
        ]);

        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        CommunicationSend::factory()->create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
        ]);

        app(CampaignFreezeService::class)->freeze($campaign, null);

        return $campaign->fresh();
    }

    public function test_blocking_reasons_include_disabled_by_default(): void
    {
        $campaign = $this->readyFrozenCampaign();

        $this->assertFalse($this->service()->isEnabled());
        $this->assertNotEmpty($this->service()->blockingReasons($campaign));
        $this->assertFalse($this->service()->canTestSend($campaign));
    }

    public function test_blocking_reasons_include_not_frozen_even_when_enabled(): void
    {
        config(['communication.real_send_enabled' => true]);

        $sender = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);

        $this->assertFalse($this->service()->canTestSend($campaign));
    }

    public function test_can_test_send_true_when_enabled_frozen_and_ready(): void
    {
        config(['communication.real_send_enabled' => true]);
        $campaign = $this->readyFrozenCampaign();

        $this->assertTrue($this->service()->canTestSend($campaign));
    }

    public function test_send_throws_when_not_allowed(): void
    {
        $campaign = $this->readyFrozenCampaign(); // enabled flag still false by default
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $this->expectException(RuntimeException::class);

        $this->service()->send($campaign, $subscriber, new RecordingEmailProvider, null);
    }

    public function test_send_rejects_a_subscriber_who_is_not_confirmed(): void
    {
        config(['communication.real_send_enabled' => true]);
        $campaign = $this->readyFrozenCampaign();
        $unconfirmed = CommunicationSubscriber::factory()->create(); // status=pending by default

        $this->expectException(RuntimeException::class);

        $this->service()->send($campaign, $unconfirmed, new RecordingEmailProvider, null);
    }

    public function test_send_persists_a_test_send_row_with_the_provider_outcome(): void
    {
        config(['communication.real_send_enabled' => true]);
        $actor = User::factory()->create(['role' => 'editor']);
        $campaign = $this->readyFrozenCampaign();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $testSend = $this->service()->send($campaign, $subscriber, new RecordingEmailProvider, $actor->id);

        $this->assertInstanceOf(CommunicationTestSend::class, $testSend);
        $this->assertSame(CommunicationTestSend::STATUS_ACCEPTED, $testSend->status);
        $this->assertSame($campaign->id, $testSend->campaign_id);
        $this->assertSame($subscriber->id, $testSend->subscriber_id);
        $this->assertSame($actor->id, $testSend->triggered_by);
        $this->assertDatabaseCount('comm_test_sends', 1);
    }

    public function test_send_records_exception_status_when_the_provider_throws_unexpectedly(): void
    {
        config(['communication.real_send_enabled' => true]);
        $campaign = $this->readyFrozenCampaign();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $throwingProvider = new class implements EmailDeliveryProvider
        {
            public function deliver(RenderedCampaignMessage $message): DeliveryResult
            {
                throw new RuntimeException('Errore di programmazione simulato.');
            }
        };

        $testSend = $this->service()->send($campaign, $subscriber, $throwingProvider, null);

        $this->assertSame(CommunicationTestSend::STATUS_EXCEPTION, $testSend->status);
        $this->assertSame('unexpected_exception', $testSend->failure_reason);
    }

    /**
     * Nucleo della garanzia "mai lista destinatari, mai campagna
     * inviata": un invio di test non tocca comm_sends né lo status della
     * campagna, indipendentemente da quante volte viene eseguito.
     */
    public function test_send_never_touches_comm_sends_or_the_campaign_status(): void
    {
        config(['communication.real_send_enabled' => true]);
        $campaign = $this->readyFrozenCampaign();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $sendsCountBefore = CommunicationSend::where('campaign_id', $campaign->id)->count();
        $statusBefore = $campaign->status;

        $this->service()->send($campaign, $subscriber, new RecordingEmailProvider, null);
        $this->service()->send($campaign, $subscriber, new RecordingEmailProvider, null);

        $this->assertSame($sendsCountBefore, CommunicationSend::where('campaign_id', $campaign->id)->count());
        $this->assertSame($statusBefore, $campaign->fresh()->status);
        $this->assertDatabaseCount('comm_test_sends', 2);
    }

    public function test_send_writes_an_activity_log_entry_without_the_recipient_email(): void
    {
        config(['communication.real_send_enabled' => true]);
        $campaign = $this->readyFrozenCampaign();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create(['email' => 'segreto@example.test']);

        $this->service()->send($campaign, $subscriber, new RecordingEmailProvider, null);

        $log = CommunicationCampaignActivityLog::where('campaign_id', $campaign->id)
            ->where('subject_type', 'test_send')
            ->firstOrFail();

        $this->assertStringNotContainsString('segreto@example.test', $log->action);
        $this->assertStringNotContainsString('@', $log->action.'|'.$log->old_value.'|'.$log->new_value.'|'.$log->reason);
    }
}
