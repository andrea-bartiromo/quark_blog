<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignActivityLog;
use App\Models\CommunicationSenderProfile;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationCampaignCrudTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Newsletter di prova',
            'type' => CommunicationCampaign::TYPE_NEWSLETTER,
            'subject' => 'Oggetto di prova',
            'preheader' => 'Un\'anteprima breve',
            'body' => 'Corpo semplice della campagna.',
            'description' => 'Descrizione interna di prova.',
            'internal_notes' => 'Nota interna di prova.',
        ], $overrides);
    }

    // ── Permessi: create/store ──────────────────────────────────

    public function test_a_guest_cannot_reach_the_create_form(): void
    {
        $this->get(route('admin.comunicazione.campaigns.create'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_reach_the_create_form(): void
    {
        $response = $this->actingAs($this->author())->get(route('admin.comunicazione.campaigns.create'));

        $response->assertRedirect(route('redazione.dashboard'));
    }

    public function test_an_editor_can_reach_the_create_form(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.create'));

        $response->assertOk();
    }

    public function test_an_admin_can_reach_the_create_form(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.comunicazione.campaigns.create'));

        $response->assertOk();
    }

    public function test_a_guest_cannot_store_a_campaign(): void
    {
        $this->post(route('admin.comunicazione.campaigns.store'), $this->validPayload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('comm_campaigns', 0);
    }

    public function test_an_author_cannot_store_a_campaign(): void
    {
        $this->actingAs($this->author())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload())
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseCount('comm_campaigns', 0);
    }

    // ── CRUD: store ──────────────────────────────────────────────

    public function test_an_editor_can_create_a_campaign_which_always_starts_as_draft(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.store'), $this->validPayload());

        $campaign = CommunicationCampaign::firstOrFail();
        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $campaign));

        $this->assertSame('Newsletter di prova', $campaign->title);
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->status);
        $this->assertSame('Corpo semplice della campagna.', $campaign->content['body']);
        $this->assertSame($editor->id, $campaign->created_by);
        $this->assertSame($editor->id, $campaign->updated_by);
        $this->assertNotEmpty($campaign->uuid);
    }

    public function test_an_admin_can_also_create_a_campaign(): void
    {
        $this->actingAs($this->admin())->post(route('admin.comunicazione.campaigns.store'), $this->validPayload());

        $this->assertDatabaseCount('comm_campaigns', 1);
    }

    public function test_creating_a_campaign_can_link_it_to_a_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload(['project_id' => $project->id]));

        $campaign = CommunicationCampaign::firstOrFail();
        $this->assertSame($project->id, $campaign->project_id);
    }

    public function test_creating_a_campaign_without_a_project_leaves_it_nullable(): void
    {
        $this->actingAs($this->editor())->post(route('admin.comunicazione.campaigns.store'), $this->validPayload());

        $this->assertNull(CommunicationCampaign::firstOrFail()->project_id);
    }

    // ── Mittente (Comunicazione B4A) ────────────────────────────

    public function test_creating_a_campaign_can_link_it_to_a_sender_profile(): void
    {
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload(['sender_profile_id' => $senderProfile->id]));

        $campaign = CommunicationCampaign::firstOrFail();
        $this->assertSame($senderProfile->id, $campaign->sender_profile_id);
        $this->assertTrue($campaign->senderProfile->is($senderProfile));
    }

    public function test_creating_a_campaign_without_a_sender_profile_leaves_it_nullable(): void
    {
        $this->actingAs($this->editor())->post(route('admin.comunicazione.campaigns.store'), $this->validPayload());

        $this->assertNull(CommunicationCampaign::firstOrFail()->sender_profile_id);
    }

    public function test_sender_profile_id_must_reference_an_existing_sender_profile(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload(['sender_profile_id' => 999999]));

        $response->assertSessionHasErrors('sender_profile_id');
        $this->assertDatabaseCount('comm_campaigns', 0);
    }

    public function test_an_editor_can_change_the_sender_profile_of_an_existing_campaign(): void
    {
        $campaign = CommunicationCampaign::factory()->create();
        $senderProfile = CommunicationSenderProfile::factory()->create();

        $this->actingAs($this->editor())->put(
            route('admin.comunicazione.campaigns.update', $campaign),
            $this->validPayload(['sender_profile_id' => $senderProfile->id])
        );

        $this->assertSame($senderProfile->id, $campaign->fresh()->sender_profile_id);
    }

    public function test_title_is_required(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload(['title' => '']));

        $response->assertSessionHasErrors('title');
        $this->assertDatabaseCount('comm_campaigns', 0);
    }

    public function test_subject_is_required(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload(['subject' => '']));

        $response->assertSessionHasErrors('subject');
    }

    public function test_type_must_be_one_of_the_known_options(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload(['type' => 'non-esiste']));

        $response->assertSessionHasErrors('type');
    }

    public function test_project_id_must_reference_an_existing_project(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.campaigns.store'), $this->validPayload(['project_id' => 999999]));

        $response->assertSessionHasErrors('project_id');
    }

    public function test_storing_a_campaign_records_an_activity_log_entry(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.store'), $this->validPayload());

        $campaign = CommunicationCampaign::firstOrFail();

        $this->assertDatabaseHas('comm_campaign_activity_logs', [
            'campaign_id' => $campaign->id,
            'action' => 'Campagna creata',
            'user_id' => $editor->id,
        ]);
    }

    // ── CRUD: update ─────────────────────────────────────────────

    public function test_an_editor_can_update_a_campaign(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['title' => 'Titolo originale']);

        $response = $this->actingAs($this->editor())
            ->put(route('admin.comunicazione.campaigns.update', $campaign), $this->validPayload(['title' => 'Titolo aggiornato']));

        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $campaign));
        $this->assertSame('Titolo aggiornato', $campaign->fresh()->title);
    }

    public function test_updating_a_campaign_never_changes_its_status(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['status' => CommunicationCampaign::STATUS_DRAFT]);

        $this->actingAs($this->editor())
            ->put(route('admin.comunicazione.campaigns.update', $campaign), $this->validPayload());

        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $campaign->fresh()->status);
    }

    public function test_updating_a_campaign_replaces_the_body_content(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['content' => ['body' => 'vecchio testo']]);

        $this->actingAs($this->editor())
            ->put(route('admin.comunicazione.campaigns.update', $campaign), $this->validPayload(['body' => 'nuovo testo']));

        $this->assertSame('nuovo testo', $campaign->fresh()->content['body']);
    }

    public function test_an_author_cannot_update_a_campaign(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['title' => 'Invariato']);

        $this->actingAs($this->author())
            ->put(route('admin.comunicazione.campaigns.update', $campaign), $this->validPayload())
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertSame('Invariato', $campaign->fresh()->title);
    }

    public function test_updating_a_campaign_records_an_activity_log_entry(): void
    {
        $campaign = CommunicationCampaign::factory()->create();
        $editor = $this->editor();

        $this->actingAs($editor)->put(route('admin.comunicazione.campaigns.update', $campaign), $this->validPayload());

        $this->assertDatabaseHas('comm_campaign_activity_logs', [
            'campaign_id' => $campaign->id,
            'action' => 'Campagna modificata',
            'user_id' => $editor->id,
        ]);
    }

    // ── CRUD: destroy ────────────────────────────────────────────

    public function test_an_editor_can_delete_a_draft_campaign(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $response = $this->actingAs($this->editor())->delete(route('admin.comunicazione.campaigns.destroy', $campaign));

        $response->assertRedirect(route('admin.comunicazione.campaigns.index'));
        $this->assertSoftDeleted('comm_campaigns', ['id' => $campaign->id]);
    }

    public function test_a_deleted_campaign_no_longer_appears_in_the_index(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['title' => 'Da eliminare']);
        $this->actingAs($this->editor())->delete(route('admin.comunicazione.campaigns.destroy', $campaign));

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.index'));

        $response->assertDontSee('Da eliminare');
    }

    public function test_an_author_cannot_delete_a_campaign(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $this->actingAs($this->author())
            ->delete(route('admin.comunicazione.campaigns.destroy', $campaign))
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseHas('comm_campaigns', ['id' => $campaign->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_campaign_records_an_activity_log_entry(): void
    {
        $campaign = CommunicationCampaign::factory()->create(['title' => 'Da eliminare con log']);
        $editor = $this->editor();

        $this->actingAs($editor)->delete(route('admin.comunicazione.campaigns.destroy', $campaign));

        $this->assertDatabaseHas('comm_campaign_activity_logs', [
            'campaign_id' => $campaign->id,
            'action' => 'Campagna eliminata',
            'subject_title' => 'Da eliminare con log',
            'user_id' => $editor->id,
        ]);
    }

    // ── Duplicazione ─────────────────────────────────────────────

    public function test_duplicating_a_campaign_copies_its_content_with_a_new_uuid_and_draft_status(): void
    {
        $original = CommunicationCampaign::factory()->completed()->create([
            'title' => 'Originale',
            'subject' => 'Oggetto originale',
            'content' => ['body' => 'testo originale'],
            'scheduled_at' => now()->addDays(2),
        ]);

        $response = $this->actingAs($this->editor())->post(route('admin.comunicazione.campaigns.duplicate', $original));

        $copy = CommunicationCampaign::where('id', '!=', $original->id)->firstOrFail();
        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $copy));

        $this->assertSame('Originale (copia)', $copy->title);
        $this->assertSame('Oggetto originale', $copy->subject);
        $this->assertSame('testo originale', $copy->content['body']);
        $this->assertSame(CommunicationCampaign::STATUS_DRAFT, $copy->status);
        $this->assertNotSame($original->uuid, $copy->uuid);
        $this->assertNull($copy->scheduled_at);
        $this->assertNull($copy->sending_started_at);
        $this->assertNull($copy->completed_at);
    }

    public function test_duplicating_a_campaign_does_not_copy_its_sends(): void
    {
        $original = CommunicationCampaign::factory()->create();
        \App\Models\CommunicationSend::factory()->for($original, 'campaign')->create();

        $this->actingAs($this->editor())->post(route('admin.comunicazione.campaigns.duplicate', $original));

        $copy = CommunicationCampaign::where('id', '!=', $original->id)->firstOrFail();

        $this->assertCount(0, $copy->sends);
        $this->assertCount(1, $original->fresh()->sends);
    }

    public function test_an_author_cannot_duplicate_a_campaign(): void
    {
        $original = CommunicationCampaign::factory()->create();

        $this->actingAs($this->author())
            ->post(route('admin.comunicazione.campaigns.duplicate', $original))
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseCount('comm_campaigns', 1);
    }

    public function test_duplicating_a_campaign_records_an_activity_log_entry(): void
    {
        $original = CommunicationCampaign::factory()->create(['title' => 'Sorgente']);
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.comunicazione.campaigns.duplicate', $original));

        $copy = CommunicationCampaign::where('id', '!=', $original->id)->firstOrFail();

        $this->assertDatabaseHas('comm_campaign_activity_logs', [
            'campaign_id' => $copy->id,
            'action' => 'Campagna duplicata da «Sorgente»',
            'user_id' => $editor->id,
        ]);
    }

    // ── Cronologia (tab Cronologia) ──────────────────────────────

    public function test_the_history_tab_shows_recorded_activity_in_reverse_chronological_order(): void
    {
        $campaign = CommunicationCampaign::factory()->create();
        CommunicationCampaignActivityLog::record(
            campaign: $campaign, subjectType: 'campaign', subjectId: $campaign->id, subjectTitle: $campaign->title,
            action: 'Prima azione', userId: null, source: CommunicationCampaignActivityLog::SOURCE_SYSTEM,
        );
        CommunicationCampaignActivityLog::record(
            campaign: $campaign, subjectType: 'campaign', subjectId: $campaign->id, subjectTitle: $campaign->title,
            action: 'Seconda azione', userId: null, source: CommunicationCampaignActivityLog::SOURCE_SYSTEM,
        );

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'history']));

        $response->assertOk();
        $order = strpos($response->getContent(), 'Seconda azione') <=> strpos($response->getContent(), 'Prima azione');
        $this->assertSame(-1, $order, 'La voce più recente deve comparire prima nella pagina.');
    }

    public function test_the_history_tab_shows_an_empty_state_for_a_campaign_with_no_activity(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'history']));

        $response->assertOk();
        $response->assertSee('Nessuna attività registrata');
    }

    // ── Placeholder ("In arrivo") ────────────────────────────────

    public function test_placeholder_tabs_show_an_in_arrivo_badge_and_no_real_content(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        foreach (['articles', 'template', 'segments', 'sends', 'stats'] as $tab) {
            $response = $this->actingAs($this->editor())
                ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => $tab]));

            $response->assertOk();
            $response->assertSee('In arrivo');
        }
    }
}
