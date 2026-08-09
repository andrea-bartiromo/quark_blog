<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\ProjectNextActionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NextActionResolver v1 — contratto deterministico:
 *   1. attività scaduta ELEGGIBILE;
 *   2. attività eleggibile in scadenza entro 7 giorni;
 *   3. prima attività eleggibile secondo l'ordinamento esistente (sort_order, created_at);
 *   4. eleggibile = non terminale AND (depends_on_task_id null OR dipendenza manual_status === completed).
 *
 * Contratto pubblico ESISTENTE (Project::suggestedNextAction()) non toccato:
 * questo è un servizio nuovo, separato, non ancora collegato ad alcuna UI.
 */
class ProjectNextActionResolverTest extends TestCase
{
    use RefreshDatabase;

    private ProjectNextActionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ProjectNextActionResolver;
    }

    // 1. Dipendenza completata -> task eleggibile.
    public function test_a_task_whose_dependency_is_completed_is_eligible(): void
    {
        $project = Project::factory()->create();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        $dependent = ProjectTask::factory()->for($project)->create([
            'title' => 'Dipendente eleggibile',
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_ELIGIBLE_NOT_STARTED, $result->kind);
        $this->assertTrue($result->task?->is($dependent));
    }

    // 2. Dipendenza non completata (in_progress) -> task NON eleggibile.
    public function test_a_task_whose_dependency_is_not_completed_is_not_eligible(): void
    {
        $project = Project::factory()->create();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_PENDING_DEPENDENCIES, $result->kind);
        $this->assertNull($result->task);
        $this->assertSame(1, $result->pendingDependencyCount);
    }

    // 3. Catena A -> B -> C: A dipende da B, B dipende da C. Solo C è completed
    // (B no): B non è eleggibile (dipendenza non soddisfatta), A nemmeno
    // (dipende da B, che non è completed, indipendentemente da C).
    public function test_a_dependency_chain_only_the_immediate_link_matters(): void
    {
        $project = Project::factory()->create();
        $c = ProjectTask::factory()->for($project)->create(['title' => 'C', 'manual_status' => ProjectTask::STATUS_COMPLETED]);
        $b = ProjectTask::factory()->for($project)->create([
            'title' => 'B',
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $c->id,
        ]);
        ProjectTask::factory()->for($project)->create([
            'title' => 'A',
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $b->id,
        ]);

        // B dipende da C (completed): B è eleggibile, e sarà la prima
        // task "da avviare" utile (A non lo è, dipende da B che non è
        // ancora completed).
        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_ELIGIBLE_NOT_STARTED, $result->kind);
        $this->assertTrue($result->task?->is($b));
    }

    // 4. Dipendenza "blocked" -> non eleggibile (qualunque stato non-completed blocca).
    public function test_a_dependency_that_is_blocked_makes_the_dependent_ineligible(): void
    {
        $project = Project::factory()->create();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_BLOCKED]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_PENDING_DEPENDENCIES, $result->kind);
    }

    // 5. Dipendenza "suspended" -> non eleggibile.
    public function test_a_dependency_that_is_suspended_makes_the_dependent_ineligible(): void
    {
        $project = Project::factory()->create();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_SUSPENDED]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_PENDING_DEPENDENCIES, $result->kind);
    }

    // 6. Dipendenza "cancelled" -> non eleggibile (il contratto minimo dice
    // letteralmente "completed", nessuna eccezione per cancelled: non è una
    // scelta di prodotto inventata qui, è il contratto così come specificato).
    public function test_a_dependency_that_is_cancelled_makes_the_dependent_ineligible(): void
    {
        $project = Project::factory()->create();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_PENDING_DEPENDENCIES, $result->kind);
    }

    // 7. Overdue ma dipendenza non completata -> NON proposta come overdue
    // (a differenza del suggestedNextAction() esistente, che non guarda le
    // dipendenze): il resolver v1 applica l'eleggibilità a TUTTE le priorità.
    public function test_an_overdue_task_with_an_incomplete_dependency_is_not_proposed(): void
    {
        $project = Project::factory()->create();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create([
            'due_date' => now()->subDays(3),
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertNotSame(ProjectNextActionResolver::KIND_OVERDUE, $result->kind);
        $this->assertSame(ProjectNextActionResolver::KIND_PENDING_DEPENDENCIES, $result->kind);
    }

    // 8. Due-soon ma dipendenza non completata -> NON proposta come due-soon.
    public function test_a_due_soon_task_with_an_incomplete_dependency_is_not_proposed(): void
    {
        $project = Project::factory()->create();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);
        ProjectTask::factory()->for($project)->create([
            'due_date' => now()->addDays(2),
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertNotSame(ProjectNextActionResolver::KIND_DUE_SOON, $result->kind);
    }

    // 9. Più task eleggibili: vince la stessa priorità/ordinamento del
    // metodo esistente (overdue più vecchia, poi sort_order/created_at).
    public function test_when_several_tasks_are_eligible_the_existing_ordering_wins(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Seconda in ordine',
            'manual_status' => ProjectTask::STATUS_TODO,
            'sort_order' => 2,
        ]);
        $first = ProjectTask::factory()->for($project)->create([
            'title' => 'Prima in ordine',
            'manual_status' => ProjectTask::STATUS_TAKEN,
            'sort_order' => 1,
        ]);

        $result = $this->resolver->resolve($project);

        $this->assertTrue($result->task?->is($first));
    }

    // 10. Idempotenza: due chiamate consecutive, stesso stato del progetto,
    // restituiscono lo stesso risultato.
    public function test_resolving_twice_in_a_row_returns_the_same_result(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Task da avviare',
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $first = $this->resolver->resolve($project);
        $second = $this->resolver->resolve($project);

        $this->assertSame($first->kind, $second->kind);
        $this->assertSame($first->task?->id, $second->task?->id);
    }

    // 11. La sola risoluzione non scrive mai nulla su DB (nessun effetto
    // collaterale): né sulla task, né sul progetto.
    public function test_resolving_never_writes_to_the_database(): void
    {
        $project = Project::factory()->create(['next_action' => 'Valore invariato']);
        $task = ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);
        // Snapshot preso DOPO la creazione della task (che già ricalcola
        // progress via ProjectTask::booted()) — così isola davvero gli
        // effetti collaterali della sola resolve(), non quelli della setup.
        $progressBefore = $project->fresh()->progress;
        $taskUpdatedAt = $task->fresh()->updated_at;
        $projectUpdatedAt = $project->fresh()->updated_at;

        $this->resolver->resolve($project);

        $this->assertSame($progressBefore, $project->fresh()->progress);
        $this->assertSame('Valore invariato', $project->fresh()->next_action);
        $this->assertTrue($task->fresh()->updated_at->equalTo($taskUpdatedAt));
        $this->assertTrue($project->fresh()->updated_at->equalTo($projectUpdatedAt));
    }

    // 12. Nessuna task non terminale -> KIND_NONE, non "pending dependencies".
    public function test_no_open_tasks_at_all_resolves_to_none(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);

        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_NONE, $result->kind);
        $this->assertNull($result->task);
    }

    // 13. Nessuna dipendenza (null) resta eleggibile come oggi — nessuna
    // regressione sul caso comune.
    public function test_a_task_without_any_dependency_is_eligible(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);

        $result = $this->resolver->resolve($project);

        $this->assertSame(ProjectNextActionResolver::KIND_ELIGIBLE_NOT_STARTED, $result->kind);
        $this->assertTrue($result->task?->is($task));
    }
}
