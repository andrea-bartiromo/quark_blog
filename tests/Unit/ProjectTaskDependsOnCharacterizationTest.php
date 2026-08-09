<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caratterizzazione (PR tests-only, nessuna correzione): depends_on_task_id
 * è dormiente — esiste in schema/relazione/fillable ma non è raggiungibile
 * da alcuna UI/form/factory oggi (verificato via grep sull'intero
 * codebase). Questi test fissano cosa succede se il valore viene
 * impostato direttamente sul modello, come base per attivarlo in
 * sicurezza in futuro (vedi report missione notturna, Task D).
 *
 * Nessuna delle protezioni verificate qui come ASSENTI (self-dependency,
 * cicli diretti/transitivi, cross-project) esiste oggi: sono documentate
 * come limite noto, non introdotte né corrette in questa PR.
 */
class ProjectTaskDependsOnCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function project(array $overrides = []): Project
    {
        return Project::factory()->create($overrides);
    }

    // 1. Eliminare la task da cui si dipende NON elimina la task dipendente:
    // depends_on_task_id viene semplicemente azzerato (nullOnDelete), non
    // cascade-delete. La dipendente torna "senza dipendenza" (eleggibile).
    public function test_deleting_the_dependency_task_nulls_the_foreign_key_instead_of_cascading(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create();
        $dependent = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $dependency->id]);

        $dependency->delete();

        $this->assertNotNull($dependent->fresh());
        $this->assertNull($dependent->fresh()->depends_on_task_id);
    }

    // 2. Self-dependency (A -> A): NESSUNA protezione oggi, il DB la accetta.
    public function test_a_task_can_currently_depend_on_itself_no_protection_exists(): void
    {
        $project = $this->project();
        $task = ProjectTask::factory()->for($project)->create();

        $task->update(['depends_on_task_id' => $task->id]);

        $this->assertSame($task->id, $task->fresh()->depends_on_task_id);
    }

    // 3. Ciclo diretto A -> B -> A: NESSUNA protezione oggi.
    public function test_a_direct_cycle_between_two_tasks_is_currently_allowed(): void
    {
        $project = $this->project();
        $a = ProjectTask::factory()->for($project)->create();
        $b = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $a->id]);

        $a->update(['depends_on_task_id' => $b->id]);

        $this->assertSame($b->id, $a->fresh()->depends_on_task_id);
        $this->assertSame($a->id, $b->fresh()->depends_on_task_id);
    }

    // 4. Ciclo transitivo A -> B -> C -> A: NESSUNA protezione oggi.
    public function test_a_transitive_cycle_across_three_tasks_is_currently_allowed(): void
    {
        $project = $this->project();
        $a = ProjectTask::factory()->for($project)->create();
        $b = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $a->id]);
        $c = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $b->id]);

        $a->update(['depends_on_task_id' => $c->id]);

        $this->assertSame($c->id, $a->fresh()->depends_on_task_id);
        $this->assertSame($a->id, $b->fresh()->depends_on_task_id);
        $this->assertSame($b->id, $c->fresh()->depends_on_task_id);
    }

    // 5. Dipendenza cross-project: una task del Progetto X può dipendere da
    // una task del Progetto Y. NESSUNA protezione oggi — la FK punta a
    // project_tasks in generale, non è mai filtrata per project_id.
    public function test_a_task_can_currently_depend_on_a_task_from_a_different_project(): void
    {
        $projectX = $this->project(['title' => 'Progetto X']);
        $projectY = $this->project(['title' => 'Progetto Y']);
        $taskInY = ProjectTask::factory()->for($projectY)->create();

        $taskInX = ProjectTask::factory()->for($projectX)->create(['depends_on_task_id' => $taskInY->id]);

        $this->assertSame($taskInY->id, $taskInX->fresh()->depends_on_task_id);
        $this->assertTrue($taskInX->fresh()->dependsOn->project->is($projectY));
    }

    // 6. depends_on_task_id non è raggiungibile dal form HTTP: anche
    // inviandolo esplicitamente nella richiesta, StoreProjectTaskRequest
    // non lo valida/whitelista, quindi non viene mai salvato da lì.
    public function test_depends_on_task_id_is_not_settable_through_the_task_creation_form(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $project = $this->project();
        $existingTask = ProjectTask::factory()->for($project)->create();

        $this->actingAs($editor)->post(route('admin.progettazione.projects.tasks.store', $project), [
            'title' => 'Nuova attività',
            'type' => ProjectTask::TYPE_TASK,
            'manual_status' => ProjectTask::STATUS_TODO,
            'priority' => ProjectTask::PRIORITY_MEDIUM,
            'depends_on_task_id' => $existingTask->id,
        ]);

        $created = ProjectTask::where('title', 'Nuova attività')->firstOrFail();
        $this->assertNull($created->depends_on_task_id);
    }

    // 7. Nessuna dipendenza impostata di default: la factory non la genera
    // mai spontaneamente (va impostata esplicitamente, come sopra).
    public function test_the_factory_never_sets_a_dependency_by_default(): void
    {
        $project = $this->project();
        $task = ProjectTask::factory()->for($project)->create();

        $this->assertNull($task->depends_on_task_id);
    }
}
