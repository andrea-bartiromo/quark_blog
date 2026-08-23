<?php

namespace Tests\Unit\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignActivityLog;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use App\Services\Communication\CampaignFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CampaignFreezeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CampaignFreezeService
    {
        return app(CampaignFreezeService::class);
    }

    /**
     * Costruisce una campagna che supera la verifica pre-invio (mittente
     * attivo, oggetto e corpo valorizzati, almeno un destinatario
     * preparato) — la stessa soglia di readiness già richiesta da
     * preflight/dry-run, riusata qui invece di reinventarne una propria.
     */
    private function readyCampaign(): CommunicationCampaign
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

        return $campaign;
    }

    public function test_a_campaign_that_fails_preflight_cannot_be_frozen(): void
    {
        $campaign = CommunicationCampaign::factory()->create([
            'subject' => 'Oggetto',
            'content' => null,
        ]);

        $this->assertFalse($this->service()->canFreeze($campaign));
    }

    public function test_a_ready_campaign_can_be_frozen(): void
    {
        $campaign = $this->readyCampaign();

        $this->assertTrue($this->service()->canFreeze($campaign));
    }

    public function test_an_already_frozen_campaign_cannot_be_frozen_again(): void
    {
        $campaign = $this->readyCampaign();
        $this->service()->freeze($campaign, null);

        $this->assertFalse($this->service()->canFreeze($campaign->fresh()));
    }

    public function test_a_trashed_campaign_cannot_be_frozen(): void
    {
        $campaign = $this->readyCampaign();
        $campaign->delete();

        $trashed = CommunicationCampaign::withTrashed()->findOrFail($campaign->id);

        $this->assertFalse($this->service()->canFreeze($trashed));
    }

    public function test_freezing_a_not_ready_campaign_throws(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['content' => null]);

        $this->expectException(RuntimeException::class);

        $this->service()->freeze($campaign, null);
    }

    public function test_freeze_sets_frozen_at_and_frozen_by_and_returns_true(): void
    {
        $actor = User::factory()->create(['role' => 'editor']);
        $campaign = $this->readyCampaign();

        $result = $this->service()->freeze($campaign, $actor->id);

        $this->assertTrue($result);
        $campaign->refresh();
        $this->assertNotNull($campaign->frozen_at);
        $this->assertSame($actor->id, $campaign->frozen_by);
        $this->assertTrue($campaign->isFrozen());
    }

    /**
     * Nucleo della garanzia di idempotenza: una seconda chiamata a
     * freeze() sulla stessa campagna è un no-op silenzioso — nessun
     * cambiamento a frozen_at, nessuna seconda riga di audit log,
     * indipendentemente da quante volte viene richiamata.
     */
    public function test_freezing_twice_is_idempotent_and_does_not_duplicate_the_audit_log(): void
    {
        $campaign = $this->readyCampaign();

        $firstResult = $this->service()->freeze($campaign, null);
        $frozenAtAfterFirst = $campaign->fresh()->frozen_at;

        $secondResult = $this->service()->freeze($campaign->fresh(), null);

        $this->assertTrue($firstResult);
        $this->assertFalse($secondResult);
        $this->assertTrue($campaign->fresh()->frozen_at->equalTo($frozenAtAfterFirst));
        $this->assertSame(
            1,
            CommunicationCampaignActivityLog::where('campaign_id', $campaign->id)
                ->where('subject_type', 'freeze')
                ->count()
        );
    }

    public function test_the_activity_log_entry_records_actor_timestamp_recipient_count_and_content_version_without_secrets(): void
    {
        $actor = User::factory()->create(['role' => 'editor', 'name' => 'Redattrice di prova']);
        $campaign = $this->readyCampaign();

        // Un secondo destinatario, cosi' il conteggio nel log non e' 1
        // per costruzione (rende l'asserzione sul valore reale, non su
        // una coincidenza con il default della fixture).
        $secondSubscriber = CommunicationSubscriber::factory()->confirmed()->create();
        CommunicationSend::factory()->create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $secondSubscriber->id,
        ]);

        $this->service()->freeze($campaign, $actor->id);

        $log = CommunicationCampaignActivityLog::where('campaign_id', $campaign->id)
            ->where('subject_type', 'freeze')
            ->firstOrFail();

        $this->assertSame($actor->id, $log->user_id);
        $this->assertNotNull($log->created_at);
        $this->assertSame($campaign->id, $log->campaign_id);
        $this->assertSame('2', $log->new_value);
        $this->assertSame('contenuto_manuale', $log->reason);

        $payload = $log->action.'|'.$log->old_value.'|'.$log->new_value.'|'.$log->reason;
        $this->assertStringNotContainsString('@', $payload);
        $this->assertStringNotContainsString('smtp', mb_strtolower($payload));
        $this->assertStringNotContainsString('password', mb_strtolower($payload));
    }
}
