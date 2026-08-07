<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_author_collaborator_cannot_access_the_projects_index(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.projects.index'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.progettazione.projects.index'))
            ->assertRedirect(route('login'));
    }

    public function test_editor_can_see_the_projects_index(): void
    {
        Project::factory()->create(['title' => 'Speciale Enigma']);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.index'))
            ->assertOk()
            ->assertSeeText('Speciale Enigma');
    }

    public function test_editor_can_create_a_project(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.progettazione.projects.store'), [
            'title' => 'Speciale Enigma',
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_HIGH,
        ]);

        $project = Project::firstWhere('title', 'Speciale Enigma');
        $response->assertRedirect(route('admin.progettazione.projects.show', $project));
        $this->assertNotNull($project);
        $this->assertSame('speciale-enigma', $project->slug);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Progetto creato',
        ]);
    }

    public function test_creating_a_project_with_a_duplicate_title_does_not_error_and_gets_a_distinct_slug(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.progettazione.projects.store'), [
            'title' => 'Speciale Enigma',
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_HIGH,
        ])->assertRedirect();

        $second = $this->actingAs($editor)->post(route('admin.progettazione.projects.store'), [
            'title' => 'Speciale Enigma',
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_HIGH,
        ]);

        $second->assertSessionHasNoErrors();
        $second->assertStatus(302);

        $project = Project::firstWhere('slug', 'speciale-enigma-2');
        $this->assertNotNull($project);
    }

    public function test_editor_can_view_a_project_overview(): void
    {
        $project = Project::factory()->create(['title' => 'Speciale Enigma', 'description' => 'Descrizione di prova']);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', $project))
            ->assertOk()
            ->assertSeeText('Speciale Enigma')
            ->assertSeeText('Descrizione di prova');
    }

    public function test_editor_can_update_a_project(): void
    {
        $project = Project::factory()->create(['title' => 'Titolo originale']);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => 'Titolo aggiornato',
            'type' => $project->type,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
        ])->assertRedirect(route('admin.progettazione.projects.show', $project));

        $this->assertSame('Titolo aggiornato', $project->fresh()->title);
        $this->assertSame('titolo-originale', $project->fresh()->slug);
    }

    public function test_quick_status_change_updates_status_without_the_full_form(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_IDEA]);

        $this->actingAs($this->editor())
            ->patch(route('admin.progettazione.projects.update-status', $project), [
                'operational_status' => Project::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect(route('admin.progettazione.projects.show', $project));

        $this->assertSame(Project::STATUS_IN_PROGRESS, $project->fresh()->operational_status);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Stato progetto cambiato da «Idea» a «In lavorazione»',
        ]);
    }

    public function test_quick_status_change_to_the_same_status_does_not_create_a_duplicate_log_entry(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_IDEA]);

        $this->actingAs($this->editor())->patch(route('admin.progettazione.projects.update-status', $project), [
            'operational_status' => Project::STATUS_IDEA,
        ]);

        $this->assertSame(0, ProjectActivityLog::where('project_id', $project->id)->count());
    }

    public function test_editor_can_delete_a_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->editor())
            ->delete(route('admin.progettazione.projects.destroy', $project))
            ->assertRedirect(route('admin.progettazione.projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_index_can_be_filtered_by_status(): void
    {
        Project::factory()->create(['title' => 'Bloccato', 'operational_status' => Project::STATUS_BLOCKED]);
        Project::factory()->create(['title' => 'In corso', 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.index', ['status' => Project::STATUS_BLOCKED]));

        $response->assertSeeText('Bloccato');
        $response->assertDontSeeText('In corso');
    }

    public function test_index_orders_projects_by_priority_severity_not_alphabetically(): void
    {
        Project::factory()->create(['title' => 'Media', 'priority' => Project::PRIORITY_MEDIUM]);
        Project::factory()->create(['title' => 'Critica', 'priority' => Project::PRIORITY_CRITICAL]);
        Project::factory()->create(['title' => 'Bassa', 'priority' => Project::PRIORITY_LOW]);
        Project::factory()->create(['title' => 'Alta', 'priority' => Project::PRIORITY_HIGH]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.index'));

        // Isola il corpo della tabella: la sidebar contiene un link "Media"
        // che altrimenti falserebbe la ricerca testuale su tutta la pagina.
        $content = strstr($response->getContent(), 'admin-table');
        $posCritica = strpos($content, 'Critica');
        $posAlta = strpos($content, 'Alta');
        $posMedia = strpos($content, 'Media');
        $posBassa = strpos($content, 'Bassa');

        $this->assertTrue($posCritica < $posAlta && $posAlta < $posMedia && $posMedia < $posBassa);
    }

    public function test_history_tab_lists_activity_log_entries_newest_first(): void
    {
        $project = Project::factory()->create();
        $editor = $this->editor();

        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Progetto creato', $editor->id);
        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Stato progetto cambiato', $editor->id);

        $response = $this->actingAs($editor)
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Stato progetto cambiato') < strpos($content, 'Progetto creato'));
    }

    public function test_history_tab_shows_an_empty_state_when_no_activity_yet(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']))
            ->assertSeeText('Nessuna attività registrata ancora.');
    }

    /**
     * Rifinitura UX: "Elimina progetto" deve essere chiaramente visibile
     * nella pagina dettaglio, non solo raggiungibile via API/rotta.
     */
    public function test_delete_project_action_is_visible_on_the_project_detail_page(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.show', $project));

        $response->assertSeeText('Elimina progetto');
        $response->assertSee('action="'.route('admin.progettazione.projects.destroy', $project).'"', false);
    }

    public function test_delete_project_action_is_visible_on_the_edit_form(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.edit', $project));

        $response->assertSeeText('Elimina progetto');
    }

    /**
     * Rifinitura UX: il cambio rapido di stato deve essere un widget
     * riconoscibile (classe dedicata), non una <select> isolata.
     */
    public function test_quick_status_switcher_widget_is_present_on_the_detail_page(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.show', $project));

        $response->assertSee('project-status-switcher', false);
        $response->assertSeeText('Stato progetto');
    }

    /**
     * Rifinitura UX: le schede del dettaglio progetto usano una classe CSS
     * dedicata (bar leggibile, scrollabile su mobile) invece di stili
     * inline a basso contrasto per i tab non attivi.
     */
    public function test_project_tabs_use_the_dedicated_readable_tab_bar_class(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.show', $project));

        $response->assertSee('class="project-tabs"', false);

        foreach (['Panoramica', 'Roadmap', 'Attività', 'Articoli', 'Documenti', 'Prompt', 'Decisioni', 'Cronologia'] as $tab) {
            $response->assertSeeText($tab);
        }
    }

    // ── Blocco E: avanzamento automatico e suggerimento prossima azione ──

    public function test_progress_field_is_read_only_in_the_form(): void
    {
        $project = Project::factory()->create(['progress' => 40]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.edit', $project));

        $response->assertOk();
        $response->assertDontSee('name="progress"', false);
        $response->assertSeeText('40%');
    }

    public function test_submitting_a_progress_value_from_the_form_is_ignored(): void
    {
        $project = Project::factory()->create(['progress' => 0]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => $project->title,
            'type' => $project->type,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
            'progress' => 99,
        ]);

        // 0 task completate su 1 = 0%, non il 99 inviato dal form.
        $this->assertSame(0, $project->fresh()->progress);
    }

    public function test_edit_form_shows_a_suggested_next_action_with_an_apply_button(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Attività da avviare',
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.edit', $project));

        $response->assertOk();
        $response->assertSeeText('Avviare l\'attività: «Attività da avviare»');
        $response->assertSee('Applica', false);
    }

    public function test_new_project_form_shows_no_suggestion(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.create'));

        $response->assertOk();
        $response->assertDontSee('💡 Suggerimento', false);
    }
}
