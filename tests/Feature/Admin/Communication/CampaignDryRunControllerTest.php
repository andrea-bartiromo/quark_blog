<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignDryRunControllerTest extends TestCase
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

        CommunicationSend::create([
            'campaign_id' => $campaign->id,
            'subscriber_id' => CommunicationSubscriber::factory()->confirmed()->create()->id,
            'status' => CommunicationSend::STATUS_QUEUED,
        ]);

        return $campaign;
    }

    public function test_running_a_dry_run_on_a_ready_campaign_shows_the_numeric_report(): void
    {
        $campaign = $this->readyCampaign();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.dry-run', $campaign));

        $response->assertOk();
        $response->assertSee('Esito dry-run');
        $response->assertSee('Accettati (simulati)');
    }

    public function test_a_not_ready_campaign_is_redirected_back_to_preflight_without_running(): void
    {
        $campaign = CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => null,
        ]);

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.dry-run', $campaign));

        $response->assertRedirect(route('admin.comunicazione.campaigns.preflight', $campaign));
        $response->assertSessionHasErrors('dry_run');
    }

    public function test_the_dry_run_page_never_offers_a_send_action(): void
    {
        $campaign = $this->readyCampaign();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.dry-run', $campaign));

        $response->assertOk();
        $response->assertDontSee('>Invia<', false);
    }

    public function test_dry_run_never_mutates_the_database_through_the_http_endpoint(): void
    {
        $campaign = $this->readyCampaign();

        $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.dry-run', $campaign));

        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
        $this->assertDatabaseHas('comm_sends', ['campaign_id' => $campaign->id, 'status' => CommunicationSend::STATUS_QUEUED]);
    }

    public function test_dry_run_requires_editor_authorization(): void
    {
        $campaign = $this->readyCampaign();
        $author = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($author)
            ->post(route('admin.comunicazione.campaigns.dry-run', $campaign));

        $response->assertRedirect(route('redazione.dashboard'));
    }

    public function test_preflight_page_offers_a_dry_run_button_only_when_ready(): void
    {
        $ready = $this->readyCampaign();
        $notReady = CommunicationCampaign::factory()->draft()->create(['sender_profile_id' => null]);

        $readyResponse = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $ready));
        $readyResponse->assertSee('Esegui dry-run');

        $notReadyResponse = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preflight', $notReady));
        $notReadyResponse->assertDontSee('Esegui dry-run');
    }
}
