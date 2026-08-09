<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use App\Services\Communication\RecipientSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Newsletter 2.0 — primo incremento "Prepara destinatari": crea lo
 * snapshot dei destinatari SENZA inviare alcuna email. Vedi
 * RecipientSnapshotService per il ragionamento su idempotenza,
 * eleggibilità e concorrenza.
 */
class RecipientSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_it_never_sends_any_email(): void
    {
        Mail::fake();

        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->confirmed()->count(5)->create();

        app(RecipientSnapshotService::class)->prepare($campaign);

        Mail::assertNothingSent();
    }

    public function test_it_creates_a_queued_send_row_for_each_confirmed_subscriber(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        $confirmed = CommunicationSubscriber::factory()->confirmed()->count(3)->create();

        $result = app(RecipientSnapshotService::class)->prepare($campaign);

        $this->assertSame(3, $result['added']);
        $this->assertSame(0, $result['already_present']);
        $this->assertSame(3, $result['eligible_total']);
        $this->assertSame(3, CommunicationSend::where('campaign_id', $campaign->id)->count());

        foreach ($confirmed as $subscriber) {
            $this->assertDatabaseHas('comm_sends', [
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'status' => CommunicationSend::STATUS_QUEUED,
            ]);
        }
    }

    public function test_it_excludes_pending_unsubscribed_bounced_and_complained_subscribers(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_PENDING]);
        CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED]);
        CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_BOUNCED]);
        CommunicationSubscriber::factory()->create(['status' => CommunicationSubscriber::STATUS_COMPLAINED]);
        CommunicationSubscriber::factory()->confirmed()->create();

        $result = app(RecipientSnapshotService::class)->prepare($campaign);

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_running_it_twice_does_not_duplicate_rows(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->confirmed()->count(4)->create();

        $service = app(RecipientSnapshotService::class);
        $first = $service->prepare($campaign);
        $second = $service->prepare($campaign);

        $this->assertSame(4, $first['added']);
        $this->assertSame(0, $second['added']);
        $this->assertSame(4, $second['already_present']);
        $this->assertSame(4, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_running_it_again_picks_up_newly_confirmed_subscribers_without_touching_existing_rows(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->confirmed()->count(2)->create();

        $service = app(RecipientSnapshotService::class);
        $service->prepare($campaign);

        $existingSendId = CommunicationSend::where('campaign_id', $campaign->id)->first()->id;

        CommunicationSubscriber::factory()->confirmed()->count(3)->create();
        $second = $service->prepare($campaign);

        $this->assertSame(3, $second['added']);
        $this->assertSame(5, CommunicationSend::where('campaign_id', $campaign->id)->count());
        // La riga preesistente non viene ricreata (stesso id, non un nuovo record).
        $this->assertDatabaseHas('comm_sends', ['id' => $existingSendId, 'campaign_id' => $campaign->id]);
    }

    public function test_a_subscriber_who_unsubscribes_after_the_snapshot_keeps_their_existing_queued_row(): void
    {
        // Comportamento deliberato: lo snapshot è additivo, non
        // riconciliante. La verifica dello stato al momento dell'invio
        // reale è responsabilità del futuro step di invio, non di questo.
        $campaign = CommunicationCampaign::factory()->draft()->create();
        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();

        $service = app(RecipientSnapshotService::class);
        $service->prepare($campaign);

        $subscriber->update([
            'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        $service->prepare($campaign);

        $this->assertDatabaseHas('comm_sends', [
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $this->assertSame(1, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_it_is_not_allowed_for_a_sending_completed_failed_or_cancelled_campaign(): void
    {
        $service = app(RecipientSnapshotService::class);

        foreach ([
            CommunicationCampaign::STATUS_SENDING,
            CommunicationCampaign::STATUS_COMPLETED,
            CommunicationCampaign::STATUS_FAILED,
            CommunicationCampaign::STATUS_CANCELLED,
        ] as $status) {
            $campaign = CommunicationCampaign::factory()->create(['status' => $status]);

            $this->assertFalse($service->canPrepare($campaign));
            $this->expectException(\RuntimeException::class);
            $service->prepare($campaign);
        }
    }

    public function test_it_is_allowed_for_draft_and_scheduled_campaigns(): void
    {
        $service = app(RecipientSnapshotService::class);

        $draft = CommunicationCampaign::factory()->draft()->create();
        $scheduled = CommunicationCampaign::factory()->scheduled()->create();

        $this->assertTrue($service->canPrepare($draft));
        $this->assertTrue($service->canPrepare($scheduled));
    }

    public function test_controller_action_requires_editor_role(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $campaign = CommunicationCampaign::factory()->draft()->create();

        $response = $this->actingAs($author)->post(route('admin.comunicazione.campaigns.recipients.prepare', $campaign));

        $response->assertRedirect(route('redazione.dashboard'));
        $this->assertSame(0, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_controller_action_creates_the_snapshot_and_logs_activity(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->confirmed()->count(2)->create();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.recipients.prepare', $campaign));

        $response->assertRedirect(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'sends']));
        $response->assertSessionHas('success');
        $this->assertSame(2, CommunicationSend::where('campaign_id', $campaign->id)->count());
        $this->assertDatabaseHas('comm_campaign_activity_logs', [
            'campaign_id' => $campaign->id,
            'subject_type' => 'recipients',
        ]);
    }

    public function test_controller_action_rejects_a_completed_campaign_with_no_side_effects(): void
    {
        $campaign = CommunicationCampaign::factory()->completed()->create();
        CommunicationSubscriber::factory()->confirmed()->create();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.recipients.prepare', $campaign));

        $response->assertSessionHasErrors('recipients');
        $this->assertSame(0, CommunicationSend::where('campaign_id', $campaign->id)->count());
    }

    public function test_sends_tab_shows_recipient_counts_and_the_prepare_button(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create();
        CommunicationSubscriber::factory()->confirmed()->count(3)->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'sends']));

        $response->assertOk();
        $response->assertSee('Destinatari pronti');
        $response->assertSee('Prepara destinatari');
        $response->assertSee('NON invia alcuna email', false);
    }
}
