<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\CommunicationTemplateVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Newsletter settimanale',
            'description' => 'Template per la newsletter di ogni venerdì.',
            'type' => CommunicationCampaign::TYPE_NEWSLETTER,
            'subject' => 'Le novità della settimana',
            'preheader' => 'Un riassunto veloce',
            'body' => 'Corpo del template.',
        ], $overrides);
    }

    // ── Permessi ─────────────────────────────────────────────────

    public function test_a_guest_cannot_reach_the_templates_index(): void
    {
        $this->get(route('admin.comunicazione.templates.index'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_reach_the_templates_index(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.comunicazione.templates.index'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_an_author_cannot_store_a_template(): void
    {
        $this->actingAs($this->author())
            ->post(route('admin.comunicazione.templates.store'), $this->validPayload())
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseCount('comm_templates', 0);
    }

    // ── CRUD: store ──────────────────────────────────────────────

    public function test_an_editor_can_create_a_template_with_its_first_version(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->post(route('admin.comunicazione.templates.store'), $this->validPayload());

        $template = CommunicationTemplate::firstOrFail();
        $response->assertRedirect(route('admin.comunicazione.templates.show', $template));

        $this->assertSame('Newsletter settimanale', $template->name);
        $this->assertSame(CommunicationTemplate::STATUS_ACTIVE, $template->status);
        $this->assertNotEmpty($template->uuid);
        $this->assertNotNull($template->active_version_id);

        $version = $template->activeVersion;
        $this->assertSame(1, $version->version_number);
        $this->assertSame('Le novità della settimana', $version->subject);
        $this->assertSame('Corpo del template.', $version->content['body']);
        $this->assertSame($editor->id, $version->created_by);
    }

    public function test_name_is_required(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.templates.store'), $this->validPayload(['name' => '']));

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('comm_templates', 0);
    }

    public function test_an_admin_can_also_create_a_template(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.comunicazione.templates.store'), $this->validPayload());

        $this->assertDatabaseCount('comm_templates', 1);
    }

    public function test_subject_is_required(): void
    {
        $response = $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.templates.store'), $this->validPayload(['subject' => '']));

        $response->assertSessionHasErrors('subject');
    }

    public function test_type_is_optional_and_means_all_campaign_types(): void
    {
        $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.templates.store'), $this->validPayload(['type' => '']));

        $this->assertNull(CommunicationTemplate::firstOrFail()->type);
    }

    // ── Versionamento (Blocco 4) ───────────────────────────────────

    public function test_updating_a_template_creates_a_new_version_instead_of_overwriting(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'subject' => 'Oggetto v1']);
        $template->update(['active_version_id' => $v1->id]);

        $this->actingAs($this->editor())->put(
            route('admin.comunicazione.templates.update', $template),
            $this->validPayload(['subject' => 'Oggetto v2', 'status' => CommunicationTemplate::STATUS_ACTIVE])
        );

        $template->refresh();

        $this->assertCount(2, $template->versions);
        $this->assertSame('Oggetto v1', $v1->fresh()->subject, 'La versione precedente non deve mai essere modificata.');
        $this->assertSame('Oggetto v2', $template->activeVersion->subject);
        $this->assertSame(2, $template->activeVersion->version_number);
    }

    public function test_a_campaign_pinned_to_an_old_version_is_unaffected_by_a_template_update(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'subject' => 'Oggetto v1']);
        $template->update(['active_version_id' => $v1->id]);
        $campaign = CommunicationCampaign::factory()->create(['template_id' => $template->id, 'template_version_id' => $v1->id]);

        $this->actingAs($this->editor())->put(
            route('admin.comunicazione.templates.update', $template),
            $this->validPayload(['subject' => 'Oggetto v2', 'status' => CommunicationTemplate::STATUS_ACTIVE])
        );

        $this->assertSame('Oggetto v1', $campaign->fresh()->templateVersion->subject);
    }

    public function test_editing_can_also_change_the_template_status(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create();
        $template->update(['active_version_id' => $v1->id]);

        $this->actingAs($this->editor())->put(
            route('admin.comunicazione.templates.update', $template),
            $this->validPayload(['status' => CommunicationTemplate::STATUS_ARCHIVED])
        );

        $this->assertSame(CommunicationTemplate::STATUS_ARCHIVED, $template->fresh()->status);
    }

    // ── Duplica template ─────────────────────────────────────────

    public function test_duplicating_a_template_creates_an_independent_copy_with_its_own_version_1(): void
    {
        $template = CommunicationTemplate::factory()->create(['name' => 'Originale']);
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'subject' => 'Oggetto originale']);
        $template->update(['active_version_id' => $v1->id]);

        $this->actingAs($this->editor())->post(route('admin.comunicazione.templates.duplicate', $template));

        $copy = CommunicationTemplate::where('id', '!=', $template->id)->firstOrFail();

        $this->assertSame('Originale (copia)', $copy->name);
        $this->assertSame(1, $copy->activeVersion->version_number);
        $this->assertSame('Oggetto originale', $copy->activeVersion->subject);
        $this->assertNotSame($template->uuid, $copy->uuid);
    }

    // ── Duplica da una versione passata (Blocco 4) ─────────────────

    public function test_duplicating_from_an_old_version_creates_a_new_version_and_leaves_history_intact(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'subject' => 'Oggetto v1']);
        $v2 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 2, 'subject' => 'Oggetto v2']);
        $template->update(['active_version_id' => $v2->id]);

        $this->actingAs($this->editor())->post(route('admin.comunicazione.templates.versions.duplicate', [$template, $v1]));

        $template->refresh();

        $this->assertCount(3, $template->versions);
        $this->assertSame(3, $template->activeVersion->version_number);
        $this->assertSame('Oggetto v1', $template->activeVersion->subject);
        // Le versioni precedenti restano intatte.
        $this->assertSame('Oggetto v1', $v1->fresh()->subject);
        $this->assertSame('Oggetto v2', $v2->fresh()->subject);
    }

    public function test_duplicating_a_version_that_does_not_belong_to_the_template_is_rejected(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $otherTemplate = CommunicationTemplate::factory()->create();
        $foreignVersion = CommunicationTemplateVersion::factory()->for($otherTemplate, 'template')->create();

        $this->actingAs($this->editor())
            ->post(route('admin.comunicazione.templates.versions.duplicate', [$template, $foreignVersion]))
            ->assertNotFound();
    }

    // ── Archivia / Elimina ───────────────────────────────────────

    public function test_an_editor_can_archive_an_active_template(): void
    {
        $template = CommunicationTemplate::factory()->create();

        $this->actingAs($this->editor())->post(route('admin.comunicazione.templates.archive', $template));

        $this->assertSame(CommunicationTemplate::STATUS_ARCHIVED, $template->fresh()->status);
    }

    public function test_archived_templates_are_excluded_from_the_active_scope(): void
    {
        CommunicationTemplate::factory()->create();
        $archived = CommunicationTemplate::factory()->archived()->create();

        $this->assertFalse(CommunicationTemplate::active()->get()->contains($archived));
    }

    public function test_a_template_with_no_linked_campaigns_can_be_deleted(): void
    {
        $template = CommunicationTemplate::factory()->create();

        $response = $this->actingAs($this->editor())->delete(route('admin.comunicazione.templates.destroy', $template));

        $response->assertRedirect(route('admin.comunicazione.templates.index'));
        $this->assertSoftDeleted('comm_templates', ['id' => $template->id]);
    }

    public function test_a_template_used_by_a_campaign_cannot_be_deleted(): void
    {
        $template = CommunicationTemplate::factory()->create();
        CommunicationCampaign::factory()->create(['template_id' => $template->id]);

        $response = $this->actingAs($this->editor())->delete(route('admin.comunicazione.templates.destroy', $template));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('comm_templates', ['id' => $template->id, 'deleted_at' => null]);
    }

    // ── Preview ──────────────────────────────────────────────────

    public function test_preview_shows_the_active_version_by_default(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'subject' => 'Oggetto v1']);
        $v2 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 2, 'subject' => 'Oggetto v2']);
        $template->update(['active_version_id' => $v2->id]);

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.templates.preview', $template));

        $response->assertOk();
        $response->assertSee('Oggetto v2');
        $response->assertDontSee('Oggetto v1');
    }

    public function test_preview_can_show_a_specific_past_version(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 1, 'subject' => 'Oggetto v1']);
        $v2 = CommunicationTemplateVersion::factory()->for($template, 'template')->create(['version_number' => 2, 'subject' => 'Oggetto v2']);
        $template->update(['active_version_id' => $v2->id]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.templates.preview', ['template' => $template, 'versione' => $v1->id]));

        $response->assertOk();
        $response->assertSee('Oggetto v1');
        $response->assertDontSee('Oggetto v2');
    }

    public function test_preview_never_mentions_sending_an_email(): void
    {
        $template = CommunicationTemplate::factory()->create();
        $v1 = CommunicationTemplateVersion::factory()->for($template, 'template')->create();
        $template->update(['active_version_id' => $v1->id]);

        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.templates.preview', $template));

        $response->assertDontSee('Invia ora');
        $response->assertDontSee('Invio di prova');
    }

    // ── Empty state ──────────────────────────────────────────────

    public function test_the_index_shows_an_empty_state_with_creation_cta(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.comunicazione.templates.index'));

        $response->assertOk();
        $response->assertSee('Nessun template trovato');
        $response->assertSee('Nuovo template');
    }

    public function test_the_status_filter_returns_only_matching_templates(): void
    {
        $active = CommunicationTemplate::factory()->create(['name' => 'Attivo di prova']);
        CommunicationTemplate::factory()->archived()->create(['name' => 'Archiviato di prova']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.templates.index', ['status' => CommunicationTemplate::STATUS_ACTIVE]));

        $response->assertSee($active->name);
        $response->assertDontSee('Archiviato di prova');
    }
}
