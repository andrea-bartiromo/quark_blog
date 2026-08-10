<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectAction\NextActionSuggestion;
use App\Services\ProjectAction\ProjectNextActionResolverV2;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectNextActionResolverV2Test extends TestCase
{
    use RefreshDatabase;

    private function resolver(): ProjectNextActionResolverV2
    {
        return app(ProjectNextActionResolverV2::class);
    }

    private function project(array $overrides = []): Project
    {
        return Project::factory()->create($overrides);
    }

    // ── Nessun segnale ────────────────────────────────────────────────

    public function test_a_project_with_nothing_open_is_aligned(): void
    {
        $project = $this->project();

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('aligned', $suggestion->code);
        $this->assertSame(NextActionSuggestion::SEVERITY_INFO, $suggestion->severity);
        $this->assertFalse($suggestion->requiresHumanDecision);
    }

    public function test_a_completed_project_is_never_flagged_overdue_even_with_a_past_due_date(): void
    {
        $project = $this->project([
            'operational_status' => Project::STATUS_COMPLETED,
            'due_date' => now()->subDays(5),
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('aligned', $suggestion->code);
    }

    // ── Segnali task (delega a ProjectNextActionResolver v1) ──────────

    public function test_an_overdue_task_produces_an_urgent_task_signal(): void
    {
        $project = $this->project();
        $task = ProjectTask::factory()->for($project)->create([
            'title' => 'Attività scaduta',
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDays(2),
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('task_overdue', $suggestion->code);
        $this->assertSame(NextActionSuggestion::SEVERITY_URGENT, $suggestion->severity);
        $this->assertStringContainsString('Attività scaduta', $suggestion->label);
        $this->assertSame('project_task', $suggestion->entityType);
        $this->assertSame($task->id, $suggestion->entityId);
    }

    public function test_a_task_pending_on_an_unmet_dependency_produces_an_attention_signal(): void
    {
        $project = $this->project();
        $dependency = ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_IN_PROGRESS]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'depends_on_task_id' => $dependency->id,
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('task_blocked_by_dependency', $suggestion->code);
        $this->assertStringContainsString('1 attività', $suggestion->label);
    }

    // ── Segnali GitHub ──────────────────────────────────────────────

    public function test_a_merged_pr_with_a_task_not_completed_produces_an_urgent_github_signal(): void
    {
        $project = $this->project();
        $task = ProjectTask::factory()->development()->for($project)->create([
            'title' => 'Refactor servizio X',
            'manual_status' => ProjectTask::STATUS_BLOCKED,
            'derived_status' => ProjectTask::DERIVED_GH_PR_MERGED,
            'status_source' => ProjectTask::SOURCE_DERIVED,
            'github_pr_number' => 42,
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('github_pr_merged_unconfirmed', $suggestion->code);
        $this->assertStringContainsString('#42', $suggestion->label);
        $this->assertStringContainsString('Refactor servizio X', $suggestion->label);
        $this->assertSame($task->id, $suggestion->entityId);
    }

    public function test_a_merged_pr_with_a_task_already_completed_produces_no_github_signal(): void
    {
        $project = $this->project();
        ProjectTask::factory()->development()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_COMPLETED,
            'derived_status' => ProjectTask::DERIVED_GH_PR_MERGED,
            'status_source' => ProjectTask::SOURCE_DERIVED,
            'github_pr_number' => 7,
        ]);

        $resolver = $this->resolver();
        $codes = array_map(
            fn ($s) => $s->code,
            [$resolver->resolve($project), ...$resolver->secondarySignals($project)]
        );

        $this->assertNotContains('github_pr_merged_unconfirmed', $codes);
    }

    // ── Segnali calendario editoriale ──────────────────────────────

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ], $overrides));
    }

    public function test_a_project_without_a_calendar_never_produces_editorial_signals(): void
    {
        $project = $this->project();

        $suggestion = $this->resolver()->resolve($project);

        $this->assertNotSame('editorial_calendar', $suggestion->source);
    }

    public function test_a_calendar_entry_needing_review_produces_an_urgent_editorial_signal(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Titolo duplicato']);
        $this->article(['title' => 'Titolo duplicato']);
        ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'content' => "28/08/2026 — Titolo duplicato\n",
            'is_editorial_calendar' => true,
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('editorial_requires_review', $suggestion->code);
        $this->assertSame(NextActionSuggestion::SEVERITY_URGENT, $suggestion->severity);
        $this->assertTrue($suggestion->requiresHumanDecision);
    }

    public function test_a_missing_calendar_article_produces_an_attention_editorial_signal_naming_the_nearest(): void
    {
        $project = $this->project();
        ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'content' => "30/08/2026 — Titolo più lontano\n29/08/2026 — Titolo più vicino\n",
            'is_editorial_calendar' => true,
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('editorial_missing_article', $suggestion->code);
        $this->assertStringContainsString('Titolo più vicino', $suggestion->label);
    }

    // ── Priorità tra fonti ──────────────────────────────────────────

    public function test_an_overdue_task_takes_priority_over_a_missing_calendar_article(): void
    {
        $project = $this->project();
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDay(),
        ]);
        ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'content' => "28/08/2026 — Un titolo senza articolo\n",
            'is_editorial_calendar' => true,
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('task_overdue', $suggestion->code);
    }

    // ── Segnali di progetto ───────────────────────────────────────────

    public function test_a_project_past_its_due_date_produces_an_urgent_project_signal(): void
    {
        $project = $this->project(['due_date' => now()->subDays(3)]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('project_overdue', $suggestion->code);
    }

    public function test_a_project_with_every_task_completed_suggests_marking_it_complete(): void
    {
        $project = $this->project();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_COMPLETED]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame('project_completable', $suggestion->code);
    }

    // ── Segnali secondari ─────────────────────────────────────────────

    public function test_secondary_signals_exclude_the_primary_and_respect_the_limit(): void
    {
        $project = $this->project(['due_date' => now()->subDays(3)]);
        ProjectTask::factory()->for($project)->create([
            'manual_status' => ProjectTask::STATUS_TODO,
            'due_date' => now()->subDay(),
        ]);

        $primary = $this->resolver()->resolve($project);
        $secondary = $this->resolver()->secondarySignals($project);

        $this->assertNotContains($primary->code, array_map(fn ($s) => $s->code, $secondary));
    }
}
