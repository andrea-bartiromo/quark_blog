<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectPrompt;
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
