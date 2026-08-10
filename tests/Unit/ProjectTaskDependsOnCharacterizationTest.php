<?php

namespace Tests\Unit;

use App\Exceptions\InvalidTaskDependencyException;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * depends_on_task_id resta dormiente (non raggiungibile da alcuna UI/form
 * — verificato via StoreProjectTaskRequest/UpdateProjectTaskRequest), ma il
 * grafo delle dipendenze è ora protetto a livello di modello: nessuna
 * auto-dipendenza, nessun ciclo diretto o transitivo, nessuna dipendenza
 * cross-project — vedi ProjectTask::guardAgainstInvalidDependency()
 * (FASE 2, missione automazione dashboard v2). Prima di questa missione
 * nessuna di queste protezioni esisteva (vedi git blame): questo file
 * fissava allora la loro ASSENZA, ora fissa la loro PRESENZA.
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

    // 2. Self-dependency (A -> A): rifiutata.
    public function test_a_task_cannot_depend_on_itself(): void
    {
        $project = $this->project();
        $task = ProjectTask::factory()->for($project)->create();

        $this->expectException(InvalidTaskDependencyException::class);

        $task->update(['depends_on_task_id' => $task->id]);
    }

    // 3. Ciclo diretto A -> B -> A: rifiutato.
    public function test_a_direct_cycle_between_two_tasks_is_rejected(): void
    {
        $project = $this->project();
        $a = ProjectTask::factory()->for($project)->create();
        $b = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $a->id]);

        $this->expectException(InvalidTaskDependencyException::class);

        $a->update(['depends_on_task_id' => $b->id]);
    }

    // 4. Ciclo transitivo A -> B -> C -> A: rifiutato.
    public function test_a_transitive_cycle_across_three_tasks_is_rejected(): void
    {
        $project = $this->project();
        $a = ProjectTask::factory()->for($project)->create();
        $b = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $a->id]);
        $c = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $b->id]);

        $this->expectException(InvalidTaskDependencyException::class);

        $a->update(['depends_on_task_id' => $c->id]);
    }

    // 5. Dipendenza cross-project: rifiutata (V1 — dipendenze sempre interne
    // allo stesso progetto, nessuna decisione architetturale la ammette).
    public function test_a_task_cannot_depend_on_a_task_from_a_different_project(): void
    {
        $projectX = $this->project(['title' => 'Progetto X']);
        $projectY = $this->project(['title' => 'Progetto Y']);
        $taskInY = ProjectTask::factory()->for($projectY)->create();

        $this->expectException(InvalidTaskDependencyException::class);

        ProjectTask::factory()->for($projectX)->create(['depends_on_task_id' => $taskInY->id]);
    }

    /**
     * Regressione Codex (PR #157, P2): spostare una task con una
     * dipendenza già valida in un altro progetto (project_id è mass
     * assignable) non toccava mai depends_on_task_id, quindi la guardia
     * — attiva solo su isDirty('depends_on_task_id') — non si
     * riattivava mai, lasciando una dipendenza cross-project non
     * rilevata. Deve rifiutare anche quando cambia solo project_id.
     */
    public function test_moving_a_task_with_an_existing_dependency_to_another_project_is_rejected(): void
    {
        $projectX = $this->project(['title' => 'Progetto X']);
        $projectY = $this->project(['title' => 'Progetto Y']);
        $dependency = ProjectTask::factory()->for($projectX)->create();
        $dependent = ProjectTask::factory()->for($projectX)->create(['depends_on_task_id' => $dependency->id]);

        $this->expectException(InvalidTaskDependencyException::class);

        $dependent->update(['project_id' => $projectY->id]);
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

    // 8. Una dipendenza valida (stesso progetto, nessun ciclo) resta
    // ammessa: la guardia rifiuta solo i grafi non validi, non ogni uso.
    public function test_a_valid_same_project_acyclic_dependency_is_still_allowed(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create();
        $dependent = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $dependency->id]);

        $this->assertSame($dependency->id, $dependent->fresh()->depends_on_task_id);
    }

    // 9. Rimuovere una dipendenza (tornare a null) non passa mai dalla
    // guardia — nessuna eccezione, nessun ciclo possibile su null.
    public function test_clearing_a_dependency_never_triggers_the_guard(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create();
        $dependent = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $dependency->id]);

        $dependent->update(['depends_on_task_id' => null]);

        $this->assertNull($dependent->fresh()->depends_on_task_id);
    }

    // 10. Salvare una task senza toccare depends_on_task_id (già impostato
    // in precedenza) non ri-attraversa la guardia: isDirty() la esclude.
    public function test_resaving_a_task_without_changing_its_dependency_never_triggers_the_guard(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create();
        $dependent = ProjectTask::factory()->for($project)->create(['depends_on_task_id' => $dependency->id]);

        $dependent->update(['title' => 'Titolo aggiornato']);

        $this->assertSame('Titolo aggiornato', $dependent->fresh()->title);
        $this->assertSame($dependency->id, $dependent->fresh()->depends_on_task_id);
    }
}
