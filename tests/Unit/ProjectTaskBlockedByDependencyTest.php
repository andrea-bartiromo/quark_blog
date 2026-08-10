<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProjectTask::isBlockedByDependency() è un fatto DERIVATO (dipendenza non
 * ancora completata), mai da confondere con lo stato editoriale esplicito
 * ProjectTask::STATUS_BLOCKED (una decisione umana in manual_status) — vedi
 * il commento sul metodo. I due concetti sono indipendenti: questo test lo
 * verifica esplicitamente in entrambe le direzioni.
 */
class ProjectTaskBlockedByDependencyTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        return Project::factory()->create();
    }

    public function test_a_task_without_a_dependency_is_never_blocked_by_dependency(): void
    {
        $task = ProjectTask::factory()->for($this->project())->create(['depends_on_task_id' => null]);

        $this->assertFalse($task->isBlockedByDependency());
    }

    public function test_a_task_depending_on_a_not_yet_completed_task_is_blocked_by_dependency(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        $dependent = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $dependency->id]);

        $this->assertTrue($dependent->isBlockedByDependency());
    }

    public function test_a_task_depending_on_a_completed_task_is_not_blocked_by_dependency(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        $dependent = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $dependency->id]);

        $this->assertFalse($dependent->isBlockedByDependency());
    }

    /**
     * Il concetto è indipendente dallo stato editoriale esplicito
     * "Bloccata": una task può essere manual_status = STATUS_BLOCKED senza
     * avere alcuna dipendenza — non è "bloccata da dipendenza".
     */
    public function test_an_editorially_blocked_task_without_a_dependency_is_not_blocked_by_dependency(): void
    {
        $task = ProjectTask::factory()->for($this->project())->create([
            'manual_status' => ProjectTask::STATUS_BLOCKED,
            'depends_on_task_id' => null,
        ]);

        $this->assertFalse($task->isBlockedByDependency());
    }

    /**
     * E viceversa: una task "Da fare" (non bloccata editorialmente) può
     * comunque essere bloccata da una dipendenza non soddisfatta.
     */
    public function test_a_todo_task_can_be_blocked_by_dependency_without_being_editorially_blocked(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);
        $dependent = ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $this->assertTrue($dependent->isBlockedByDependency());
        $this->assertNotSame(ProjectTask::STATUS_BLOCKED, $dependent->manual_status);
    }
}
