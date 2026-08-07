<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationCampaignTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function makeTemplateWithVersion(array $overrides = []): CommunicationTemplate
    {
        $template = CommunicationTemplate::factory()->create();
        $version = CommunicationTemplateVersion::factory()->for($template, 'template')->create(array_merge([
            'version_number' => 1,
            'subject' => 'Oggetto dal template',
            'preheader' => 'Preheader dal template',
            'content' => ['body' => 'Corpo dal template.'],
        ], $overrides));
        $template->update(['active_version_id' => $version->id]);

        return $template->fresh();
    }

    public function test_selecting_a_template_prefills_blank_fields_on_the_create_form(): void
    {
        $template = $this->makeTemplateWithVersion();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.create', ['template_id' => $template->id]));

        $response->assertOk();
        $response->assertSee('Oggetto dal template', false);
        $response->assertSee('Corpo dal template.', false);
    }

    public function test_storing_a_campaign_with_a_template_persists_the_link(): void
    {
        $template = $this->makeTemplateWithVersion();

        $response = $this->actingAs($this->editor())->post(route('admin.comunicazione.campaigns.store'), [
            'title' => 'Newsletter con template',
            'type' => CommunicationCampaign::TYPE_NEWSLETTER,
            'subject' => 'Oggetto dal template',
            'preheader' => 'Preheader dal template',
            'body' => 'Corpo dal template.',
            'template_id' => $template->id,
            'template_version_id' => $template->active_version_id,
        ]);

        $campaign = CommunicationCampaign::firstOrFail();
        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $campaign));
        $this->assertSame($template->id, $campaign->template_id);
        $this->assertSame($template->active_version_id, $campaign->template_version_id);
    }

    public function test_a_campaign_can_be_created_without_any_template(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.comunicazione.campaigns.store'), [
            'title' => 'Newsletter senza template',
            'type' => CommunicationCampaign::TYPE_NEWSLETTER,
            'subject' => 'Oggetto libero',
            'body' => 'Corpo libero.',
        ]);

        $campaign = CommunicationCampaign::firstOrFail();
        $response->assertRedirect(route('admin.comunicazione.campaigns.show', $campaign));
        $this->assertNull($campaign->template_id);
        $this->assertNull($campaign->template_version_id);
    }

    public function test_selecting_a_template_on_an_existing_campaign_never_overwrites_an_already_written_subject(): void
    {
        $template = $this->makeTemplateWithVersion();

        $campaign = CommunicationCampaign::factory()->create([
            'subject' => 'Oggetto già scritto dalla redazione',
            'preheader' => null,
            'content' => ['body' => null],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.edit', [$campaign, 'template_id' => $template->id]));

        $response->assertOk();
        $response->assertSee('Oggetto già scritto dalla redazione', false);
        $response->assertDontSee('Oggetto dal template', false);
        $response->assertSee('Preheader dal template', false);
        $response->assertSee('Corpo dal template.', false);
    }

    public function test_choosing_no_template_clears_the_prefill_columns(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.edit', [$campaign, 'template_id' => '']));

        $response->assertOk();
    }

    public function test_only_active_templates_appear_in_the_template_picker(): void
    {
        $active = $this->makeTemplateWithVersion(['subject' => 'Attivo']);
        $archived = CommunicationTemplate::factory()->archived()->create(['name' => 'Template archiviato']);

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.campaigns.create'));

        $response->assertOk();
        $response->assertSee($active->name, false);
        $response->assertDontSee($archived->name, false);
    }

    public function test_the_template_tab_shows_the_linked_template_and_version(): void
    {
        $template = $this->makeTemplateWithVersion();
        $campaign = CommunicationCampaign::factory()->create([
            'template_id' => $template->id,
            'template_version_id' => $template->active_version_id,
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'template']));

        $response->assertOk();
        $response->assertSee($template->name, false);
        $response->assertSee('v1', false);
    }

    public function test_the_template_tab_shows_no_template_linked_when_none_is_set(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.show', [$campaign, 'tab' => 'template']));

        $response->assertOk();
        $response->assertSee('Nessun template collegato', false);
    }

    public function test_a_guest_cannot_reach_the_campaign_preview(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $this->get(route('admin.comunicazione.campaigns.preview', $campaign))
            ->assertRedirect(route('login'));
    }

    public function test_the_campaign_preview_shows_the_campaigns_own_content(): void
    {
        $campaign = CommunicationCampaign::factory()->create([
            'subject' => 'Oggetto della campagna',
            'content' => ['body' => 'Corpo della campagna.'],
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        $response->assertSee('Oggetto della campagna', false);
        $response->assertSee('Corpo della campagna.', false);
    }

    public function test_the_campaign_preview_never_mentions_a_send_action(): void
    {
        $campaign = CommunicationCampaign::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        $response->assertDontSee('Invia ora', false);
        $response->assertDontSee('Invio di prova', false);
    }

    // ── Regressione: pinning versione (vedi review PR #116) ─────────

    public function test_a_campaign_pinned_to_v1_stays_on_v1_after_the_template_advances_to_v2_and_an_unrelated_field_is_saved(): void
    {
        $editor = $this->editor();
        $template = $this->makeTemplateWithVersion();
        $v1Id = $template->active_version_id;

        $campaign = CommunicationCampaign::factory()->create([
            'template_id' => $template->id,
            'template_version_id' => $v1Id,
        ]);

        // Il template avanza a una v2 (nuova versione attiva).
        $this->actingAs($editor)->put(route('admin.comunicazione.templates.update', $template), [
            'name' => $template->name,
            'status' => CommunicationTemplate::STATUS_ACTIVE,
            'subject' => 'Oggetto v2',
            'preheader' => 'Preheader v2',
            'body' => 'Corpo v2.',
        ]);

        $this->assertNotSame($v1Id, $template->fresh()->active_version_id);

        // Apertura della form di modifica SENZA selezionare esplicitamente un template:
        // i campi nascosti devono continuare a puntare alla versione già fissata (v1).
        $editResponse = $this->actingAs($editor)->get(route('admin.comunicazione.campaigns.edit', $campaign));
        $editResponse->assertOk();
        $editResponse->assertSee('name="template_version_id" value="'.$v1Id.'"', false);

        // Salvataggio di un campo non correlato al template.
        $this->actingAs($editor)->put(route('admin.comunicazione.campaigns.update', $campaign), [
            'title' => 'Titolo aggiornato',
            'type' => $campaign->type,
            'subject' => $campaign->subject,
            'template_id' => $template->id,
            'template_version_id' => $v1Id,
        ]);

        $this->assertSame($v1Id, $campaign->fresh()->template_version_id);
    }

    public function test_a_campaign_archived_templates_link_is_preserved_when_editing_without_reselecting(): void
    {
        $editor = $this->editor();
        $template = $this->makeTemplateWithVersion();
        $versionId = $template->active_version_id;

        $campaign = CommunicationCampaign::factory()->create([
            'template_id' => $template->id,
            'template_version_id' => $versionId,
        ]);

        $template->update(['status' => CommunicationTemplate::STATUS_ARCHIVED]);

        $editResponse = $this->actingAs($editor)->get(route('admin.comunicazione.campaigns.edit', $campaign));
        $editResponse->assertOk();
        $editResponse->assertSee('name="template_id" value="'.$template->id.'"', false);
        $editResponse->assertSee('name="template_version_id" value="'.$versionId.'"', false);
    }

    // ── Regressione: coerenza coppia template/versione ───────────────

    public function test_storing_a_campaign_with_a_version_from_a_different_template_is_rejected(): void
    {
        $templateA = $this->makeTemplateWithVersion();
        $templateB = $this->makeTemplateWithVersion();

        $response = $this->actingAs($this->editor())->post(route('admin.comunicazione.campaigns.store'), [
            'title' => 'Newsletter incoerente',
            'type' => CommunicationCampaign::TYPE_NEWSLETTER,
            'subject' => 'Oggetto',
            'body' => 'Corpo.',
            'template_id' => $templateA->id,
            'template_version_id' => $templateB->active_version_id,
        ]);

        $response->assertSessionHasErrors('template_version_id');
        $this->assertDatabaseCount('comm_campaigns', 0);
    }

    public function test_updating_a_campaign_with_a_version_from_a_different_template_is_rejected(): void
    {
        $templateA = $this->makeTemplateWithVersion();
        $templateB = $this->makeTemplateWithVersion();

        $campaign = CommunicationCampaign::factory()->create([
            'template_id' => $templateA->id,
            'template_version_id' => $templateA->active_version_id,
        ]);

        $response = $this->actingAs($this->editor())->put(route('admin.comunicazione.campaigns.update', $campaign), [
            'title' => $campaign->title,
            'type' => $campaign->type,
            'subject' => $campaign->subject,
            'template_id' => $templateA->id,
            'template_version_id' => $templateB->active_version_id,
        ]);

        $response->assertSessionHasErrors('template_version_id');
        $this->assertSame($templateA->active_version_id, $campaign->fresh()->template_version_id);
    }

    // ── Regressione: template selezionato non va perso dopo un errore di validazione ───

    public function test_the_selected_template_survives_a_validation_failure_on_create(): void
    {
        $template = $this->makeTemplateWithVersion();
        $editor = $this->editor();

        $response = $this->actingAs($editor)->from(route('admin.comunicazione.campaigns.create'))->post(
            route('admin.comunicazione.campaigns.store'),
            [
                'title' => '',
                'type' => CommunicationCampaign::TYPE_NEWSLETTER,
                'subject' => 'Oggetto',
                'template_id' => $template->id,
                'template_version_id' => $template->active_version_id,
            ]
        );

        $response->assertSessionHasErrors('title');

        $followUp = $this->actingAs($editor)->get(route('admin.comunicazione.campaigns.create'));

        $followUp->assertOk();
        $followUp->assertSee('name="template_id" value="'.$template->id.'"', false);
    }
}
