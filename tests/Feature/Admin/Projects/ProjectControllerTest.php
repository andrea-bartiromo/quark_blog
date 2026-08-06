<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectActivityLog;
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
}
