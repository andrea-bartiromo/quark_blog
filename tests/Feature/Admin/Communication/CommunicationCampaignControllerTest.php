<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationCampaignControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.comunicazione.campaigns.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_non_editor_cannot_access_the_campaigns_list(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($author)->get(route('admin.comunicazione.campaigns.index'));

        $response->assertRedirect(route('redazione.dashboard'));
    }

    public function test_an_editor_sees_the_empty_state_when_there_are_no_campaigns(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.index'));

        $response->assertOk();
        $response->assertSee('Nessuna campagna trovata');
        $response->assertDontSee('Nuova campagna');
    }

    public function test_the_list_shows_no_creation_or_edit_actions(): void
    {
        CommunicationCampaign::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.index'));

        $response->assertOk();
        $response->assertDontSee('Nuova campagna');
        $response->assertDontSee('Elimina');
        $response->assertDontSee('Duplica');
    }

    public function test_the_type_filter_returns_only_matching_campaigns(): void
    {
        $newsletter = CommunicationCampaign::factory()->create(['type' => CommunicationCampaign::TYPE_NEWSLETTER, 'title' => 'Newsletter di prova']);
        CommunicationCampaign::factory()->create(['type' => CommunicationCampaign::TYPE_COMUNICATO, 'title' => 'Comunicato di prova']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.index', ['type' => CommunicationCampaign::TYPE_NEWSLETTER]));

        $response->assertOk();
        $response->assertSee($newsletter->title);
        $response->assertDontSee('Comunicato di prova');
    }

    public function test_the_status_filter_returns_only_matching_campaigns(): void
    {
        $draft = CommunicationCampaign::factory()->draft()->create(['title' => 'Bozza di prova']);
        CommunicationCampaign::factory()->completed()->create(['title' => 'Completata di prova']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.index', ['status' => CommunicationCampaign::STATUS_DRAFT]));

        $response->assertOk();
        $response->assertSee($draft->title);
        $response->assertDontSee('Completata di prova');
    }

    public function test_the_search_field_matches_title_or_subject(): void
    {
        $byTitle = CommunicationCampaign::factory()->create(['title' => 'Aggiornamento speciale Turing', 'subject' => 'Novità']);
        $bySubject = CommunicationCampaign::factory()->create(['title' => 'Newsletter #12', 'subject' => 'Speciale energia rinnovabile']);
        $unrelated = CommunicationCampaign::factory()->create(['title' => 'Altra campagna', 'subject' => 'Altro oggetto']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.index', ['q' => 'speciale']));

        $response->assertOk();
        $response->assertSee($byTitle->title);
        $response->assertSee($bySubject->title);
        $response->assertDontSee($unrelated->title);
    }

    public function test_sorting_by_next_send_orders_scheduled_campaigns_ascending_with_nulls_last(): void
    {
        $later = CommunicationCampaign::factory()->scheduled()->create(['scheduled_at' => now()->addDays(9)]);
        $sooner = CommunicationCampaign::factory()->scheduled()->create(['scheduled_at' => now()->addDays(1)]);
        $draft = CommunicationCampaign::factory()->draft()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.index', ['sort' => 'next-send']));

        $ids = collect($response->viewData('campaigns')->items())->pluck('id')->values();

        $this->assertSame([$sooner->id, $later->id, $draft->id], $ids->all());
    }

    public function test_default_sorting_is_most_recently_created_first(): void
    {
        $older = CommunicationCampaign::factory()->create(['created_at' => now()->subDays(5)]);
        $newer = CommunicationCampaign::factory()->create(['created_at' => now()]);

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.index'));

        $ids = collect($response->viewData('campaigns')->items())->pluck('id')->values();

        $this->assertSame([$newer->id, $older->id], $ids->all());
    }

    public function test_an_editor_can_open_a_campaign_read_only_detail(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['title' => 'Dettaglio di prova']);

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.show', $campaign));

        $response->assertOk();
        $response->assertSee('Dettaglio di prova');
        $response->assertDontSee('Elimina');
    }

    public function test_pagination_is_server_side(): void
    {
        CommunicationCampaign::factory()->count(20)->create();

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.index'));

        $response->assertOk();
        $this->assertSame(15, $response->viewData('campaigns')->count());
        $this->assertSame(20, $response->viewData('campaigns')->total());
    }
}
