<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignFreezeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

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

    public function test_a_guest_is_redirected_to_login(): void
    {
        $campaign = $this->readyCampaign();

        $response = $this->post(route('admin.comunicazione.campaigns.freeze', $campaign));

        $response->assertRedirect(route('login'));
    }

    public function test_freezing_a_ready_campaign_succeeds(): void
    {
        $campaign = $this->readyCampaign();

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.freeze', $campaign));

        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $campaign));
        $this->assertTrue($campaign->fresh()->isFrozen());
    }

    public function test_freezing_a_campaign_that_fails_preflight_is_blocked(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['content' => null]);

        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.freeze', $campaign));

        $response->assertRedirect(route('admin.comunicazione.campaigns.preflight', $campaign));
        $response->assertSessionHasErrors('freeze');
        $this->assertFalse($campaign->fresh()->isFrozen());
    }

    public function test_freezing_an_already_frozen_campaign_is_a_safe_no_op(): void
    {
        $campaign = $this->readyCampaign();
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.freeze', $campaign));
        $frozenAtAfterFirst = $campaign->fresh()->frozen_at;

        $response = $this->actingAs($editor)
            ->post(route('admin.comunicazione.campaigns.freeze', $campaign));

        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $campaign));
        $response->assertSessionHasNoErrors();
        $this->assertTrue($campaign->fresh()->frozen_at->equalTo($frozenAtAfterFirst));
    }

    public function test_updating_a_frozen_campaign_is_rejected_and_content_is_unchanged(): void
    {
        $campaign = $this->readyCampaign();
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.freeze', $campaign));

        $response = $this->actingAs($editor)->put(route('admin.comunicazione.campaigns.update', $campaign), [
            'title' => 'Titolo modificato dopo il congelamento',
            'type' => $campaign->type,
            'subject' => 'Oggetto modificato dopo il congelamento',
            'body' => 'Corpo modificato dopo il congelamento.',
        ]);

        $response->assertSessionHasErrors('freeze');
        $campaign->refresh();
        $this->assertSame('Oggetto di prova', $campaign->subject);
        $this->assertSame('Corpo di prova.', $campaign->content['body']);
    }

    public function test_editing_a_frozen_campaign_redirects_away_instead_of_showing_the_form(): void
    {
        $campaign = $this->readyCampaign();
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.freeze', $campaign));

        $response = $this->actingAs($editor)->get(route('admin.comunicazione.campaigns.edit', $campaign));

        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $campaign));
    }

    public function test_preparing_recipients_on_a_frozen_campaign_is_rejected_even_for_a_newly_confirmed_subscriber(): void
    {
        $campaign = $this->readyCampaign();
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.freeze', $campaign));
        $preparedCountAfterFreeze = $campaign->sends()->count();

        // Un iscritto che si conferma DOPO il congelamento non deve mai
        // finire nell'elenco destinatari di questa campagna.
        CommunicationSubscriber::factory()->confirmed()->create();

        $response = $this->actingAs($editor)
            ->post(route('admin.comunicazione.campaigns.recipients.prepare', $campaign));

        $response->assertSessionHasErrors('recipients');
        $this->assertSame($preparedCountAfterFreeze, $campaign->sends()->count());
    }

    public function test_the_campaign_page_shows_the_frozen_badge_and_hides_edit_and_prepare_actions(): void
    {
        $campaign = $this->readyCampaign();
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.freeze', $campaign));

        $response = $this->actingAs($editor)
            ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'sends']));

        $response->assertOk();
        $response->assertSee('Congelata');
        $response->assertDontSee('Modifica campagna');
        $response->assertDontSee('Prepara destinatari');
        $response->assertDontSee('Aggiorna destinatari');
    }

    public function test_the_freeze_button_is_not_shown_for_a_campaign_that_is_not_ready(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['content' => null]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.show', $campaign));

        $response->assertOk();
        $response->assertDontSee('Congela campagna');
    }
}
