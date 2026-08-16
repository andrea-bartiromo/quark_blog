<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignPreflightTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function readyCampaign(): CommunicationCampaign
    {
        $sender = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto reale',
            'content' => ['body' => 'Corpo reale.'],
        ]);

        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $subscriber->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);

        return $campaign;
    }

    public function test_a_fully_valid_campaign_is_ready_for_test_send(): void
    {
        $campaign = $this->readyCampaign();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertOk();
        $response->assertSee('Pronta per un invio di test');
        $response->assertDontSee('⛔ Blocchi', false);
    }

    public function test_a_bare_draft_campaign_reports_every_blocking_reason(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => null,
            'subject' => '',
            'content' => ['blocks' => []],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertOk();
        $response->assertSee('Non pronta');
        $response->assertSee('Nessun mittente collegato alla campagna.');
        $response->assertSee("Manca l'oggetto della campagna.");
        $response->assertSee('Manca il contenuto della campagna.');
        $response->assertSee('Nessun destinatario preparato');
    }

    public function test_an_archived_sender_is_a_blocking_error(): void
    {
        $campaign = $this->readyCampaign();
        $campaign->senderProfile->update(['status' => CommunicationSenderProfile::STATUS_ARCHIVED]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertOk();
        $response->assertSee('Non pronta');
        $response->assertSee('è archiviato', false);
    }

    public function test_a_campaign_no_longer_in_a_preparable_status_is_blocked(): void
    {
        $sender = CommunicationSenderProfile::factory()->create();
        $campaign = CommunicationCampaign::factory()->completed()->create([
            'sender_profile_id' => $sender->id,
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertOk();
        $response->assertSee('Non pronta');
        $response->assertSee('non è più in una fase di preparazione', false);
    }

    public function test_a_recipient_prepared_then_unsubscribed_shows_as_a_warning_not_a_block(): void
    {
        $campaign = $this->readyCampaign();
        $unsubscribed = CommunicationSubscriber::factory()->confirmed()->create();
        CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => $unsubscribed->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);
        $unsubscribed->update(['status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertOk();
        // Ancora pronta: uno stale non blocca, il motore di invio lo salterà da solo.
        $response->assertSee('Pronta per un invio di test');
        $response->assertSee('non è/sono più un iscritto confermato', false);
    }

    public function test_confirmed_subscribers_never_snapshotted_yet_are_flagged_as_a_warning(): void
    {
        $campaign = $this->readyCampaign();
        CommunicationSubscriber::factory()->confirmed()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertOk();
        $response->assertSee('non ha/hanno ancora una riga preparata', false);
    }

    public function test_the_page_never_offers_a_send_action(): void
    {
        $campaign = $this->readyCampaign();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertOk();
        $response->assertDontSee('>Invia<', false);
        $response->assertDontSee('type="submit" class="btn btn--primary">Invia', false);
    }

    public function test_preflight_never_mutates_anything_no_matter_how_many_times_its_opened(): void
    {
        $campaign = $this->readyCampaign();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->editor())
                ->get(route('admin.comunicazione.campaigns.preflight', $campaign));
        }

        $this->assertDatabaseCount('comm_sends', 1);
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
    }

    public function test_preflight_requires_editor_authorization(): void
    {
        $campaign = $this->readyCampaign();
        $author = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($author)
            ->get(route('admin.comunicazione.campaigns.preflight', $campaign));

        $response->assertRedirect(route('redazione.dashboard'));
    }
}
