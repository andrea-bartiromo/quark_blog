<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.comunicazione.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_non_editor_cannot_access_the_dashboard(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($author)->get(route('admin.comunicazione.dashboard'));

        $response->assertRedirect(route('redazione.dashboard'));
    }

    public function test_an_editor_can_view_the_dashboard(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.dashboard'));

        $response->assertOk();
        $response->assertSee('Comunicazione');
    }

    public function test_the_dashboard_counts_reflect_the_real_campaign_state(): void
    {
        CommunicationCampaign::factory()->draft()->count(2)->create();
        CommunicationCampaign::factory()->scheduled()->create(['scheduled_at' => now()->addDays(3)]);
        CommunicationCampaign::factory()->scheduled()->create(['scheduled_at' => now()->addDays(20)]);
        CommunicationCampaign::factory()->completed()->create(['completed_at' => now()->subDays(5)]);
        CommunicationCampaign::factory()->completed()->create(['completed_at' => now()->subDays(45)]);
        // Campagna dedicata (stato "sending", non conteggiato da nessuna
        // stat-card qui) cosi' il Send non crea a catena una campagna
        // draft aggiuntiva tramite il default della factory.
        $sendingCampaign = CommunicationCampaign::factory()->create(['status' => CommunicationCampaign::STATUS_SENDING]);
        CommunicationSend::factory()->failed()->for($sendingCampaign, 'campaign')->create();

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.dashboard'));

        $response->assertOk();
        $response->assertViewHas('draftCount', 2);
        // Solo una delle due programmate cade entro i prossimi 7 giorni.
        $response->assertViewHas('scheduledNext7Count', 1);
        // Solo una delle due completate cade negli ultimi 30 giorni.
        $response->assertViewHas('completedLast30Count', 1);
        $response->assertViewHas('openErrorsCount', 1);
    }

    public function test_upcoming_campaigns_widget_lists_only_scheduled_campaigns_ordered_by_date(): void
    {
        $later = CommunicationCampaign::factory()->scheduled()->create(['title' => 'Più lontana', 'scheduled_at' => now()->addDays(10)]);
        $sooner = CommunicationCampaign::factory()->scheduled()->create(['title' => 'Più vicina', 'scheduled_at' => now()->addDays(1)]);
        CommunicationCampaign::factory()->draft()->create(['title' => 'Bozza esclusa']);

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.dashboard'));

        $upcoming = $response->viewData('upcomingCampaigns');

        $this->assertCount(2, $upcoming);
        $this->assertTrue($upcoming->first()->is($sooner));
        $this->assertTrue($upcoming->last()->is($later));
    }

    public function test_provider_widget_states_it_is_not_verifiable_in_this_block(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.dashboard'));

        $response->assertSee('Non verificabile in questo blocco');
    }

    public function test_the_newsletter_link_is_still_reachable_from_the_dashboard(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.dashboard'));

        $response->assertSee(route('admin.newsletter'), false);
    }
}
