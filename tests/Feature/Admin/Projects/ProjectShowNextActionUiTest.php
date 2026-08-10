<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4, missione Dashboard Automation V2: il "Suggerimento automatico"
 * (ProjectNextActionResolverV2) e il badge di salute (ProjectHealthResolver)
 * comparivano nella pagina progetto senza mai sovrascrivere o nascondere il
 * campo manuale "Prossima azione editoriale" (next_action) già esistente.
 */
class ProjectShowNextActionUiTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_the_overview_shows_both_the_manual_and_the_automatic_suggestion_distinctly(): void
    {
        $project = Project::factory()->create(['next_action' => 'Rivedere il piano con il direttore']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', $project));

        $response->assertOk();
        $response->assertSeeText('Prossima azione editoriale');
        $response->assertSeeText('Rivedere il piano con il direttore');
        $response->assertSeeText('Suggerimento automatico');
        $response->assertSeeText('nessuna modifica automatica');
    }

    public function test_an_aligned_project_shows_the_aligned_suggestion(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', $project));

        $response->assertOk();
        $response->assertSeeText('Progetto allineato');
    }

    public function test_a_project_with_no_urgent_signal_shows_no_health_badge(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', $project));

        $response->assertOk();
        // "Bloccato" compare comunque come opzione nel selettore di stato
        // rapido: la verifica deve mirare al badge di salute (classe
        // dedicata), non al solo testo "Bloccato" ovunque nella pagina.
        $response->assertDontSee('project-health-badge', false);
    }

    public function test_a_project_with_an_overdue_task_shows_the_blocked_health_badge_on_every_tab(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDay(),
        ]);

        $overview = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', $project));
        $tasksTab = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']));

        $overview->assertOk()->assertSee('data-health="blocked"', false);
        $tasksTab->assertOk()->assertSee('data-health="blocked"', false);
    }

    public function test_the_suggestion_links_to_the_task_it_refers_to(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->create([
            'title' => 'Attività da collegare',
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', $project));

        $response->assertOk();
        $response->assertSee(route('admin.progettazione.projects.tasks.edit', [$project, $task]), false);
    }
}
