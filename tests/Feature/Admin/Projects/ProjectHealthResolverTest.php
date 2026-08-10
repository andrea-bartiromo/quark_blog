<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\ProjectAction\ProjectHealth;
use App\Services\ProjectAction\ProjectHealthResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHealthResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): ProjectHealthResolver
    {
        return app(ProjectHealthResolver::class);
    }

    private function project(array $overrides = []): Project
    {
        return Project::factory()->create($overrides);
    }

    public function test_a_project_with_nothing_open_is_ok(): void
    {
        $health = $this->resolver()->resolve($this->project());

        $this->assertSame(ProjectHealth::LEVEL_OK, $health->level);
        $this->assertSame('OK', $health->label());
    }

    public function test_an_explicitly_blocked_project_is_always_blocked_regardless_of_signals(): void
    {
        $project = $this->project(['operational_status' => Project::STATUS_BLOCKED]);

        $health = $this->resolver()->resolve($project);

        $this->assertSame(ProjectHealth::LEVEL_BLOCKED, $health->level);
    }

    public function test_an_overdue_task_makes_the_project_blocked(): void
    {
        $project = $this->project();
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDay(),
        ]);

        $health = $this->resolver()->resolve($project);

        $this->assertSame(ProjectHealth::LEVEL_BLOCKED, $health->level);
    }

    public function test_a_task_due_soon_makes_the_project_attention_not_blocked(): void
    {
        $project = $this->project();
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->addDays(2),
        ]);

        $health = $this->resolver()->resolve($project);

        $this->assertSame(ProjectHealth::LEVEL_ATTENTION, $health->level);
        $this->assertSame('Attenzione', $health->label());
    }

    /**
     * Una singola task in attesa di una dipendenza è normale
     * amministrazione (attenzione), non di per sé un'emergenza per
     * l'intero progetto (bloccato) — vedi il commento sulla mappatura in
     * ProjectHealthResolver.
     */
    public function test_a_task_blocked_by_an_unmet_dependency_is_attention_not_blocked(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $health = $this->resolver()->resolve($project);

        $this->assertSame(ProjectHealth::LEVEL_ATTENTION, $health->level);
    }

    public function test_health_exposes_the_underlying_signals(): void
    {
        $project = $this->project();
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDay(),
        ]);

        $health = $this->resolver()->resolve($project);

        $this->assertNotEmpty($health->signals);
        $this->assertSame('task_overdue', $health->signals[0]->code);
    }
}
