<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Caratterizzazione (PR tests-only, nessuna correzione): fissa il
 * comportamento ATTUALE di Project::suggestedNextAction() come base per la
 * progettazione di NextActionResolver (vedi report missione notturna,
 * Task E). Include un'anomalia nota e non corretta: la priorità 3
 * ("attività non ancora avviata") ignora completamente
 * depends_on_task_id — colonna presente in schema, con relazione Eloquent
 * dedicata (ProjectTask::dependsOn()), ma mai letta da nessuna logica
 * applicativa e mai impostabile da UI/form/factory. Vedi
 * test_a_not_started_task_is_suggested_even_when_its_dependency_is_incomplete.
 */
class ProjectSuggestedNextActionTest extends TestCase
{
    use RefreshDatabase;

    // 1. Nessuna attività -> nessun suggerimento.
    public function test_a_project_with_no_tasks_has_no_suggested_next_action(): void
    {
        $project = Project::factory()->create();

        $this->assertNull($project->suggestedNextAction());
    }

    // 2. Priorità 1: un'attività in ritardo (scaduta, non completata/annullata) vince su tutto.
    public function test_an_overdue_task_is_suggested_first(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Task in ritardo',
            'due_date' => now()->subDays(2),
            'manual_status' => ProjectTask::STATUS_IN_PROGRESS,
        ]);
        ProjectTask::factory()->for($project)->create([
            'title' => 'Task in scadenza',
            'due_date' => now()->addDays(1),
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $action = $project->suggestedNextAction();

        $this->assertStringContainsString('Task in ritardo', $action);
        $this->assertStringContainsString('Sbloccare', $action);
    }

    // 3. Tra più attività in ritardo, la più vecchia (due_date più lontana) viene proposta.
    public function test_the_most_overdue_task_is_suggested_among_several(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Ritardo lieve',
            'due_date' => now()->subDay(),
        ]);
        $oldest = ProjectTask::factory()->for($project)->create([
            'title' => 'Ritardo grave',
            'due_date' => now()->subWeek(),
        ]);

        $action = $project->suggestedNextAction();

        $this->assertStringContainsString($oldest->title, $action);
    }

    // 4. Priorità 2: nessuna attività in ritardo, ma una in scadenza entro 7 giorni.
    public function test_a_task_due_soon_is_suggested_when_nothing_is_overdue(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Task in scadenza',
            'due_date' => now()->addDays(3),
            'manual_status' => ProjectTask::STATUS_IN_PROGRESS,
        ]);

        $action = $project->suggestedNextAction();

        $this->assertStringContainsString('Task in scadenza', $action);
        $this->assertStringContainsString('Prossima scadenza', $action);
    }

    // 5. Un'attività con scadenza oltre i 7 giorni non è "in scadenza": si passa alla priorità 3.
    public function test_a_task_due_further_than_seven_days_is_not_treated_as_due_soon(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Task lontana',
            'due_date' => now()->addDays(20),
            'manual_status' => ProjectTask::STATUS_IN_PROGRESS,
        ]);
        ProjectTask::factory()->for($project)->create([
            'title' => 'Task da avviare',
            'manual_status' => ProjectTask::STATUS_TODO,
            'sort_order' => 0,
        ]);

        $action = $project->suggestedNextAction();

        $this->assertStringContainsString('Task da avviare', $action);
        $this->assertStringContainsString('Avviare', $action);
    }

    // 6. Priorità 3: nessuna scadenza rilevante, si propone la prima attività non ancora avviata (todo/taken) per sort_order.
    public function test_a_not_started_task_is_suggested_ordered_by_sort_order(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Seconda in ordine',
            'manual_status' => ProjectTask::STATUS_TODO,
            'sort_order' => 2,
        ]);
        ProjectTask::factory()->for($project)->create([
            'title' => 'Prima in ordine',
            'manual_status' => ProjectTask::STATUS_TAKEN,
            'sort_order' => 1,
        ]);

        $action = $project->suggestedNextAction();

        $this->assertStringContainsString('Prima in ordine', $action);
    }

    // 7. Attività completate/annullate/in corso/bloccate non contano mai come "da avviare".
    public function test_tasks_that_are_not_todo_or_taken_are_never_suggested_as_not_started(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_BLOCKED]);

        $this->assertNull($project->suggestedNextAction());
    }

    // 8. Un'attività scaduta ma già completata/annullata non è mai "in ritardo".
    public function test_a_completed_or_cancelled_task_past_its_due_date_is_never_suggested_as_overdue(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'due_date' => now()->subWeek(),
            'manual_status' => ProjectTask::STATUS_COMPLETED,
        ]);
        ProjectTask::factory()->for($project)->create([
            'due_date' => now()->subWeek(),
            'manual_status' => ProjectTask::STATUS_CANCELLED,
        ]);

        $this->assertNull($project->suggestedNextAction());
    }

    /**
     * 9. ANOMALIA NOTA, NON CORRETTA IN QUESTA PR: depends_on_task_id
     * esiste in schema (migrazione project_tasks) e nella relazione
     * ProjectTask::dependsOn(), ma suggestedNextAction() non la legge mai.
     * Un'attività "da avviare" viene suggerita anche se la sua dipendenza
     * non è ancora completata — un umano che segue il suggerimento
     * comincerebbe un lavoro il cui prerequisito non è pronto.
     * depends_on_task_id non è inoltre raggiungibile da alcuna UI/form
     * oggi (nessun campo nel form attività, nessuna factory state): questo
     * test la imposta direttamente sul modello, come farebbe la futura
     * automazione Progettazione (Task E, NextActionResolver) descritta nel
     * report missione notturna.
     */
    public function test_a_not_started_task_is_suggested_even_when_its_dependency_is_incomplete(): void
    {
        $project = Project::factory()->create();

        $prerequisite = ProjectTask::factory()->for($project)->create([
            'title' => 'Prerequisito non completato',
            'manual_status' => ProjectTask::STATUS_IN_PROGRESS,
            'sort_order' => 0,
        ]);

        $dependent = ProjectTask::factory()->for($project)->create([
            'title' => 'Dipende dal prerequisito',
            'manual_status' => ProjectTask::STATUS_TODO,
            'sort_order' => 1,
            'depends_on_task_id' => $prerequisite->id,
        ]);

        $action = $project->suggestedNextAction();

        // Comportamento ATTUALE (anomalia): la dipendenza incompleta non
        // viene rilevata, l'attività dipendente viene comunque proposta.
        $this->assertStringContainsString($dependent->title, $action);
        $this->assertNotNull($prerequisite->fresh());
        $this->assertNotSame(ProjectTask::STATUS_COMPLETED, $prerequisite->fresh()->manual_status);
    }
}
