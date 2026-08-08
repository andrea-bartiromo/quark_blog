<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDecision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDecisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_editor_can_register_a_decision_as_proposed(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->post(route('admin.progettazione.projects.decisions.store', $project), [
            'title' => 'Unificare percorso del segnale ed Enigma cifra',
            'context' => 'Contesto.',
            'decision' => 'Unificarle in una sequenza unica.',
            'rationale' => 'Evita duplicazioni.',
            'status' => ProjectDecision::STATUS_PROPOSED,
        ]);

        $response->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions']));
        $decision = ProjectDecision::firstWhere('project_id', $project->id);
        $this->assertSame(ProjectDecision::STATUS_PROPOSED, $decision->status);
        $this->assertNull($decision->decided_at);
    }

    public function test_approving_a_decision_sets_decided_at(): void
    {
        $project = Project::factory()->create();
        $decision = ProjectDecision::factory()->for($project)->create(['status' => ProjectDecision::STATUS_PROPOSED, 'decided_at' => null]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.decisions.update', [$project, $decision]), [
            'title' => $decision->title,
            'decision' => $decision->decision,
            'status' => ProjectDecision::STATUS_APPROVED,
        ]);

        $decision->refresh();
        $this->assertSame(ProjectDecision::STATUS_APPROVED, $decision->status);
        $this->assertNotNull($decision->decided_at);
    }

    public function test_editor_can_delete_a_decision(): void
    {
        $project = Project::factory()->create();
        $decision = ProjectDecision::factory()->for($project)->create();

        $this->actingAs($this->editor())
            ->delete(route('admin.progettazione.projects.decisions.destroy', [$project, $decision]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions']));

        $this->assertDatabaseMissing('project_decisions', ['id' => $decision->id]);
    }

    // ── Audit 0, gap #2: Cronologia sulla cancellazione ──────────────

    public function test_deleting_a_decision_records_it_in_the_activity_log_and_the_entry_survives_the_deletion(): void
    {
        $project = Project::factory()->create();
        $decision = ProjectDecision::factory()->for($project)->create(['title' => 'Decisione da eliminare']);
        $editor = $this->editor();

        $this->actingAs($editor)
            ->delete(route('admin.progettazione.projects.decisions.destroy', [$project, $decision]))
            ->assertRedirect(route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions']));

        $this->assertDatabaseMissing('project_decisions', ['id' => $decision->id]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'subject_type' => 'decision',
            'subject_id' => $decision->id,
            'subject_title' => 'Decisione da eliminare',
            'action' => 'Decisione eliminata',
            'source' => ProjectActivityLog::SOURCE_MANUAL,
            'user_id' => $editor->id,
        ]);
    }

    /**
     * Correzione #5 approvata in revisione.
     */
    public function test_decisions_tab_paginates_at_fifteen_per_page(): void
    {
        $project = Project::factory()->create();
        ProjectDecision::factory()->for($project)->count(16)->create();

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions']))
            ->assertOk()
            ->assertSee('page=2', false);
    }
}
