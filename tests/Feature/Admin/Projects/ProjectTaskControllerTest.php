<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_author_collaborator_cannot_create_a_task(): void
    {
        $project = Project::factory()->create();
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.projects.tasks.create', $project))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_create_a_plain_task(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->post(route('admin.progettazione.projects.tasks.store', $project), [
            'title' => 'Scrivere le sei modali',
            'type' => ProjectTask::TYPE_TASK,
            'manual_status' => ProjectTask::STATUS_IN_PROGRESS,
            'priority' => ProjectTask::PRIORITY_HIGH,
            'due_date' => '2026-08-20',
        ]);

        $response->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']));
        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Scrivere le sei modali',
        ]);
    }

    public function test_creating_a_publication_task_with_an_article_triggers_derived_status(): void
    {
        $project = Project::factory()->create();
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Capitolo Enigma',
            'slug' => 'capitolo-enigma',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ]);

        $this->actingAs($this->editor())->post(route('admin.progettazione.projects.tasks.store', $project), [
            'title' => 'Pubblicazione capitolo Enigma',
            'type' => ProjectTask::TYPE_PUBLICATION,
            'manual_status' => ProjectTask::STATUS_TODO,
            'priority' => ProjectTask::PRIORITY_HIGH,
            'article_id' => $article->id,
        ]);

        $task = ProjectTask::firstWhere('title', 'Pubblicazione capitolo Enigma');
        $this->assertSame(ProjectTask::DERIVED_DRAFT, $task->derived_status);
    }

    public function test_editor_can_update_a_task(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->create(['title' => 'Vecchio titolo']);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.tasks.update', [$project, $task]), [
            'title' => 'Nuovo titolo',
            'type' => $task->type,
            'manual_status' => $task->manual_status,
            'priority' => $task->priority,
        ])->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']));

        $this->assertSame('Nuovo titolo', $task->fresh()->title);
    }

    public function test_editor_can_delete_a_task(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->create();

        $this->actingAs($this->editor())
            ->delete(route('admin.progettazione.projects.tasks.destroy', [$project, $task]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']));

        $this->assertDatabaseMissing('project_tasks', ['id' => $task->id]);
    }

    // ── Audit 0, gap #2: Cronologia sulla cancellazione ──────────────

    public function test_deleting_a_task_records_it_in_the_activity_log_and_the_entry_survives_the_deletion(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->create(['title' => 'Attività da eliminare']);
        $editor = $this->editor();

        $this->actingAs($editor)
            ->delete(route('admin.progettazione.projects.tasks.destroy', [$project, $task]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']));

        $this->assertDatabaseMissing('project_tasks', ['id' => $task->id]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'subject_title' => 'Attività da eliminare',
            'action' => 'Attività eliminata',
            'source' => ProjectActivityLog::SOURCE_MANUAL,
            'user_id' => $editor->id,
        ]);
    }

    public function test_cross_project_tasks_index_lists_tasks_from_multiple_projects(): void
    {
        $projectA = Project::factory()->create(['title' => 'Progetto A']);
        $projectB = Project::factory()->create(['title' => 'Progetto B']);
        ProjectTask::factory()->for($projectA)->create(['title' => 'Task A']);
        ProjectTask::factory()->for($projectB)->create(['title' => 'Task B']);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.tasks.index-all'));

        $response->assertOk()->assertSeeText('Task A')->assertSeeText('Task B');
    }

    public function test_task_form_shows_the_article_field_only_for_publication_type_task(): void
    {
        $project = Project::factory()->create();
        $publicationTask = ProjectTask::factory()->for($project)->publication()->create();
        $plainTask = ProjectTask::factory()->for($project)->create(['type' => ProjectTask::TYPE_TASK]);

        $publicationResponse = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.tasks.edit', [$project, $publicationTask]));
        $publicationResponse->assertSee('id="article-link-group" style="display: ;"', false);

        $plainResponse = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.tasks.edit', [$project, $plainTask]));
        $plainResponse->assertSee('id="article-link-group" style="display: none;"', false);
    }

    public function test_task_form_shows_the_github_branch_field_only_for_development_type_task(): void
    {
        $project = Project::factory()->create();
        $devTask = ProjectTask::factory()->for($project)->development()->create();
        $plainTask = ProjectTask::factory()->for($project)->create(['type' => ProjectTask::TYPE_TASK]);

        $devResponse = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.tasks.edit', [$project, $devTask]));
        $devResponse->assertSee('id="github-branch-group" style="display: ;"', false);

        $plainResponse = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.tasks.edit', [$project, $plainTask]));
        $plainResponse->assertSee('id="github-branch-group" style="display: none;"', false);
    }

    public function test_editor_can_set_a_github_branch_on_a_development_task(): void
    {
        // Il test passa "per caso" senza questo fake finché l'ambiente non
        // ha un GITHUB_TOKEN configurato (il sync si ferma prima di
        // qualunque chiamata HTTP) — Http::fake() lo rende esplicito e
        // sicuro anche altrove.
        Http::fake();
        $project = Project::factory()->create();

        $this->actingAs($this->editor())->post(route('admin.progettazione.projects.tasks.store', $project), [
            'title' => 'Implementare feature X',
            'type' => ProjectTask::TYPE_DEVELOPMENT,
            'manual_status' => ProjectTask::STATUS_TODO,
            'priority' => ProjectTask::PRIORITY_MEDIUM,
            'github_branch' => 'feature/x',
        ]);

        $this->assertDatabaseHas('project_tasks', [
            'title' => 'Implementare feature X',
            'github_branch' => 'feature/x',
        ]);
    }

    public function test_task_form_shows_readable_github_sync_state(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->development()->create([
            'github_branch' => 'feature/x',
            'github_pr_number' => 7,
            'github_pr_state' => 'open',
            'github_checks_state' => 'success',
            'github_review_state' => 'approved',
            'github_synced_at' => now(),
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.tasks.edit', [$project, $task]));

        $response->assertOk();
        $response->assertSeeText('PR #7');
        $response->assertSeeText('Open');
        $response->assertSeeText('Check: success');
        $response->assertSeeText('Review: approved');
    }

    /**
     * Rifinitura UX: la vista trasversale "Attività progetti" deve avere
     * una CTA "+ Nuova attività" evidente, non solo azioni "Modifica" per
     * riga.
     */
    public function test_cross_project_tasks_index_shows_a_new_task_cta(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.tasks.index-all'));

        $response->assertSeeText('Nuova attività');
        $response->assertSee(route('admin.progettazione.tasks.create-pick-project'), false);
    }

    public function test_cross_project_tasks_index_empty_state_has_operative_text_and_cta(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.tasks.index-all'));

        $response->assertSeeText('Crea la prima attività');
    }

    /**
     * Rifinitura UX: creare un'attività dalle viste globali richiede prima
     * di scegliere il progetto — passaggio esplicito invece di un errore o
     * di un vicolo cieco.
     */
    public function test_author_collaborator_cannot_access_the_task_project_picker(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.tasks.create-pick-project'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_task_project_picker_lists_projects_with_a_link_to_create_the_task(): void
    {
        $project = Project::factory()->create(['title' => 'Speciale Enigma']);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.tasks.create-pick-project'));

        $response->assertOk();
        $response->assertSeeText('Speciale Enigma');
        $response->assertSee(route('admin.progettazione.projects.tasks.create', $project), false);
    }

    public function test_task_project_picker_shows_empty_state_with_new_project_cta_when_no_projects_exist(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.tasks.create-pick-project'));

        $response->assertOk();
        $response->assertSeeText('Non esiste ancora nessun progetto');
        $response->assertSee(route('admin.progettazione.projects.create'), false);
    }

    public function test_picking_a_project_leads_to_its_task_creation_form(): void
    {
        $project = Project::factory()->create();

        $picker = $this->actingAs($this->editor())->get(route('admin.progettazione.tasks.create-pick-project'));
        $picker->assertSee(route('admin.progettazione.projects.tasks.create', $project), false);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.tasks.create', $project))
            ->assertOk()
            ->assertSeeText($project->title);
    }
}
