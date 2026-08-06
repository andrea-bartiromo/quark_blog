<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
