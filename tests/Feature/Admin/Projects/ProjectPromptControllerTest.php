<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectPrompt;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPromptControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_editor_can_create_a_prompt(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->post(route('admin.progettazione.projects.prompts.store', $project), [
            'title' => 'Analisi FASE 0 Enigma',
            'agent' => 'Claude Code',
            'content' => 'Analizza integralmente la pagina prima di proporre modifiche.',
            'status' => ProjectPrompt::STATUS_DRAFT,
        ]);

        $response->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts']));
        $this->assertDatabaseHas('project_prompts', ['project_id' => $project->id, 'title' => 'Analisi FASE 0 Enigma']);
    }

    public function test_editor_can_duplicate_a_prompt(): void
    {
        $project = Project::factory()->create();
        $prompt = ProjectPrompt::factory()->for($project)->create([
            'title' => 'Analisi FASE 0 Enigma',
            'status' => ProjectPrompt::STATUS_COMPLETED,
            'used_at' => now(),
            'outcome' => 'Esito originale',
        ]);

        $this->actingAs($this->editor())
            ->post(route('admin.progettazione.projects.prompts.duplicate', [$project, $prompt]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts']));

        $copy = ProjectPrompt::firstWhere('title', 'Analisi FASE 0 Enigma (copia)');
        $this->assertNotNull($copy);
        $this->assertSame(ProjectPrompt::STATUS_DRAFT, $copy->status);
        $this->assertNull($copy->used_at);
        $this->assertNull($copy->outcome);
        $this->assertSame($prompt->content, $copy->content);
    }

    public function test_editor_can_delete_a_prompt(): void
    {
        $project = Project::factory()->create();
        $prompt = ProjectPrompt::factory()->for($project)->create();

        $this->actingAs($this->editor())
            ->delete(route('admin.progettazione.projects.prompts.destroy', [$project, $prompt]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts']));

        $this->assertDatabaseMissing('project_prompts', ['id' => $prompt->id]);
    }

    // ── Audit 0, gap #2: Cronologia sulla cancellazione ──────────────

    public function test_deleting_a_prompt_records_it_in_the_activity_log_and_the_entry_survives_the_deletion(): void
    {
        $project = Project::factory()->create();
        $prompt = ProjectPrompt::factory()->for($project)->create(['title' => 'Prompt da eliminare']);
        $editor = $this->editor();

        $this->actingAs($editor)
            ->delete(route('admin.progettazione.projects.prompts.destroy', [$project, $prompt]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts']));

        $this->assertDatabaseMissing('project_prompts', ['id' => $prompt->id]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'subject_type' => 'prompt',
            'subject_id' => $prompt->id,
            'subject_title' => 'Prompt da eliminare',
            'action' => 'Prompt eliminato',
            'source' => ProjectActivityLog::SOURCE_MANUAL,
            'user_id' => $editor->id,
        ]);
    }

    public function test_editor_can_link_a_prompt_to_a_task_of_the_same_project(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->development()->create();

        $this->actingAs($this->editor())->post(route('admin.progettazione.projects.prompts.store', $project), [
            'title' => 'Prompt collegato',
            'content' => 'Contenuto.',
            'status' => ProjectPrompt::STATUS_DRAFT,
            'task_id' => $task->id,
        ]);

        $prompt = ProjectPrompt::firstWhere('title', 'Prompt collegato');
        $this->assertSame($task->id, $prompt->task_id);
    }

    public function test_a_task_from_a_different_project_is_rejected_as_prompt_link(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $foreignTask = ProjectTask::factory()->for($otherProject)->development()->create();

        $response = $this->actingAs($this->editor())->post(route('admin.progettazione.projects.prompts.store', $project), [
            'title' => 'Prompt incoerente',
            'content' => 'Contenuto.',
            'status' => ProjectPrompt::STATUS_DRAFT,
            'task_id' => $foreignTask->id,
        ]);

        $response->assertSessionHasErrors('task_id');
        $this->assertDatabaseMissing('project_prompts', ['title' => 'Prompt incoerente']);
    }

    public function test_prompt_form_shows_the_task_options(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->development()->create(['title' => 'Task selezionabile']);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.prompts.create', $project));

        $response->assertOk();
        $response->assertSee('Task selezionabile');
    }

    /**
     * Correzione #5 approvata in revisione.
     */
    public function test_prompts_tab_paginates_at_fifteen_per_page(): void
    {
        $project = Project::factory()->create();
        ProjectPrompt::factory()->for($project)->count(16)->create();

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts']))
            ->assertOk()
            ->assertSee('page=2', false);
    }
}
