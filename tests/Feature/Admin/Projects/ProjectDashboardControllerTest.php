<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_author_collaborator_cannot_access_the_dashboard(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.dashboard'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_see_the_dashboard(): void
    {
        Project::factory()->create(['title' => 'Speciale Enigma', 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.dashboard'))
            ->assertOk()
            ->assertSeeText('Speciale Enigma');
    }

    /**
     * Correzione #2 approvata in revisione: "Progetti attivi" nella
     * dashboard deve ordinare per severita' reale, non alfabeticamente.
     */
    public function test_active_projects_widget_orders_by_priority_severity(): void
    {
        Project::factory()->create(['title' => 'Media', 'priority' => Project::PRIORITY_MEDIUM, 'operational_status' => Project::STATUS_IN_PROGRESS]);
        Project::factory()->create(['title' => 'Critica', 'priority' => Project::PRIORITY_CRITICAL, 'operational_status' => Project::STATUS_IN_PROGRESS]);
        Project::factory()->create(['title' => 'Bassa', 'priority' => Project::PRIORITY_LOW, 'operational_status' => Project::STATUS_IN_PROGRESS]);
        Project::factory()->create(['title' => 'Alta', 'priority' => Project::PRIORITY_HIGH, 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $content = strstr($response->getContent(), 'Progetti attivi');
        $posCritica = strpos($content, 'Critica');
        $posAlta = strpos($content, 'Alta');
        $posMedia = strpos($content, 'Media');
        $posBassa = strpos($content, 'Bassa');

        $this->assertTrue($posCritica < $posAlta && $posAlta < $posMedia && $posMedia < $posBassa);
    }

    public function test_blocked_projects_widget_shows_only_blocked_projects(): void
    {
        Project::factory()->create(['title' => 'Bloccato', 'operational_status' => Project::STATUS_BLOCKED]);
        Project::factory()->create(['title' => 'In corso', 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        // Ancorato all'intestazione della card (>...</h3>), non al testo
        // generico "Progetti bloccati": anche l'etichetta della statistica
        // in cima alla pagina lo contiene, e compare prima nel documento.
        $content = $response->getContent();
        $blockedSection = substr($content, strpos($content, '>Progetti bloccati</h3>'), 800);
        $this->assertStringContainsString('Bloccato', $blockedSection);
    }

    public function test_dashboard_reuses_the_shared_admin_grid_stats_utility(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertSee('admin-grid--stats', false);
    }

    // ── "Richiedono attenzione" (FASE 3-4-6-7, Dashboard Automation V2) ──

    public function test_a_project_with_no_signal_never_appears_in_the_attention_section(): void
    {
        Project::factory()->create(['title' => 'Progetto tranquillo', 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Nessun progetto richiede attenzione al momento.');
    }

    public function test_a_project_with_an_overdue_task_appears_in_the_attention_section(): void
    {
        $project = Project::factory()->create(['title' => 'Progetto in ritardo', 'operational_status' => Project::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Progetto in ritardo');
        $response->assertSeeText('Richiedono attenzione');
    }

    public function test_a_completed_project_never_appears_in_the_attention_section_even_if_overdue(): void
    {
        Project::factory()->create([
            'title' => 'Progetto chiuso',
            'operational_status' => Project::STATUS_COMPLETED,
            'due_date' => now()->subDays(10),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('Progetto chiuso');
    }

    public function test_pending_dependency_count_is_shown_when_greater_than_zero(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_IN_PROGRESS]);
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertOk();
        $response->assertSeeText('1 attività è in attesa di una dipendenza');
    }

    /**
     * Regressione Codex (PR #157, P2): un dipendente già completato o
     * annullato non è più "in attesa" di nulla, anche se la sua
     * dipendenza non è mai stata completata — non deve gonfiare il
     * conteggio.
     */
    public function test_pending_dependency_count_excludes_dependents_in_a_terminal_status(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_IN_PROGRESS]);
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_COMPLETED,
            'depends_on_task_id' => $dependency->id,
        ]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_CANCELLED,
            'depends_on_task_id' => $dependency->id,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('in attesa di una dipendenza');
    }

    public function test_pending_dependency_note_is_absent_when_there_are_none(): void
    {
        Project::factory()->create(['operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.dashboard'));

        $response->assertOk();
        $response->assertDontSeeText('in attesa di una dipendenza');
    }
}
