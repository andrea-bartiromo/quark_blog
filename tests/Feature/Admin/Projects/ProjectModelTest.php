<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDecision;
use App\Models\ProjectDocument;
use App\Models\ProjectPrompt;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_can_be_created_with_a_slug_derived_from_the_title(): void
    {
        $project = Project::factory()->create(['title' => 'Speciale Enigma']);

        $this->assertSame('speciale-enigma', $project->slug);
    }

    public function test_two_projects_with_the_same_title_get_distinct_slugs_instead_of_a_db_error(): void
    {
        $first = Project::factory()->create(['title' => 'Speciale Enigma']);
        $second = Project::factory()->create(['title' => 'Speciale Enigma']);

        $this->assertSame('speciale-enigma', $first->slug);
        $this->assertSame('speciale-enigma-2', $second->slug);
    }

    public function test_a_third_colliding_title_gets_the_next_available_suffix(): void
    {
        Project::factory()->create(['title' => 'Speciale Enigma']);
        Project::factory()->create(['title' => 'Speciale Enigma']);
        $third = Project::factory()->create(['title' => 'Speciale Enigma']);

        $this->assertSame('speciale-enigma-3', $third->slug);
    }

    public function test_updating_a_project_keeps_its_existing_slug(): void
    {
        $project = Project::factory()->create(['title' => 'Titolo originale']);

        $project->update(['description' => 'Nuova descrizione']);

        $this->assertSame('titolo-originale', $project->fresh()->slug);
    }

    public function test_active_scope_excludes_completed_and_cancelled_projects(): void
    {
        $active = Project::factory()->create(['operational_status' => Project::STATUS_IN_PROGRESS]);
        Project::factory()->create(['operational_status' => Project::STATUS_COMPLETED]);
        Project::factory()->create(['operational_status' => Project::STATUS_CANCELLED]);

        $result = Project::active()->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->contains($active));
    }

    public function test_order_by_priority_severity_orders_critical_first_and_low_last(): void
    {
        $low = Project::factory()->create(['priority' => Project::PRIORITY_LOW, 'title' => 'Bassa']);
        $critical = Project::factory()->create(['priority' => Project::PRIORITY_CRITICAL, 'title' => 'Critica']);
        $medium = Project::factory()->create(['priority' => Project::PRIORITY_MEDIUM, 'title' => 'Media']);
        $high = Project::factory()->create(['priority' => Project::PRIORITY_HIGH, 'title' => 'Alta']);

        $ordered = Project::query()->orderByPrioritySeverity()->pluck('id');

        $this->assertSame(
            [$critical->id, $high->id, $medium->id, $low->id],
            $ordered->all()
        );
    }

    public function test_a_project_has_many_tasks_documents_prompts_and_decisions(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create();
        ProjectDocument::factory()->for($project)->create();
        ProjectPrompt::factory()->for($project)->create();
        ProjectDecision::factory()->for($project)->create();

        $this->assertCount(1, $project->tasks);
        $this->assertCount(1, $project->documents);
        $this->assertCount(1, $project->prompts);
        $this->assertCount(1, $project->decisions);
    }

    // ── hasCalculableProgress() (FASE 5, Dashboard Automation V2) ──────

    public function test_a_project_with_no_tasks_has_no_calculable_progress(): void
    {
        $project = Project::factory()->create();

        $this->assertFalse($project->hasCalculableProgress());
    }

    public function test_a_project_with_only_cancelled_tasks_has_no_calculable_progress(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_CANCELLED]);

        $this->assertFalse($project->hasCalculableProgress());
    }

    public function test_a_project_with_at_least_one_non_cancelled_task_has_calculable_progress(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);

        $this->assertTrue($project->hasCalculableProgress());
    }

    public function test_an_unsaved_project_has_no_calculable_progress(): void
    {
        $project = new Project;

        $this->assertFalse($project->hasCalculableProgress());
    }

    public function test_a_project_can_be_linked_to_an_existing_article_via_pivot(): void
    {
        $project = Project::factory()->create();
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ]);

        $project->articles()->attach($article->id);

        $this->assertTrue($project->fresh()->articles->contains($article));
        $this->assertTrue($article->fresh()->projects->contains($project));
    }

    public function test_project_task_stores_an_article_id_without_a_real_foreign_key_constraint(): void
    {
        $task = ProjectTask::factory()->publication()->create(['article_id' => 999999]);

        $this->assertSame(999999, $task->fresh()->article_id);
    }

    public function test_project_activity_log_record_stores_all_explicit_fields(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();

        $log = ProjectActivityLog::record(
            project: $project,
            subjectType: 'project',
            subjectId: $project->id,
            subjectTitle: $project->title,
            action: 'Progetto creato',
            userId: $user->id,
        );

        $this->assertDatabaseHas('project_activity_logs', [
            'id' => $log->id,
            'project_id' => $project->id,
            'action' => 'Progetto creato',
            'user_id' => $user->id,
            'source' => ProjectActivityLog::SOURCE_MANUAL,
        ]);
    }

    // ── Progetto editoriale predefinito (Blocco A/F) ────────────────

    public function test_a_project_can_be_marked_as_the_default_editorial_project(): void
    {
        $project = Project::factory()->create(['type' => Project::TYPE_EDITORIAL_SPECIAL, 'is_default_editorial' => true]);

        $this->assertTrue($project->fresh()->is_default_editorial);
        $this->assertTrue($project->fresh()->isEditorialType());
    }

    public function test_setting_a_new_default_editorial_project_unsets_the_previous_one(): void
    {
        $first = Project::factory()->create(['type' => Project::TYPE_EDITORIAL_SPECIAL, 'is_default_editorial' => true]);
        $second = Project::factory()->create(['type' => Project::TYPE_ARTICLE_SERIES, 'is_default_editorial' => true]);

        $this->assertFalse($first->fresh()->is_default_editorial);
        $this->assertTrue($second->fresh()->is_default_editorial);
    }

    public function test_a_technical_project_cannot_be_marked_as_default_editorial(): void
    {
        $project = Project::factory()->create(['type' => Project::TYPE_TECHNICAL_IMPROVEMENT, 'is_default_editorial' => true]);

        $this->assertFalse($project->fresh()->is_default_editorial);
    }

    public function test_default_editorial_returns_null_when_none_is_set(): void
    {
        Project::factory()->create(['type' => Project::TYPE_EDITORIAL_SPECIAL, 'is_default_editorial' => false]);

        $this->assertNull(Project::defaultEditorial());
    }

    public function test_default_editorial_returns_the_flagged_active_project(): void
    {
        $project = Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_IN_PROGRESS,
        ]);

        $result = Project::defaultEditorial();

        $this->assertNotNull($result);
        $this->assertTrue($result->is($project));
    }

    public function test_default_editorial_ignores_a_flagged_project_that_is_completed(): void
    {
        Project::factory()->create([
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'is_default_editorial' => true,
            'operational_status' => Project::STATUS_COMPLETED,
        ]);

        $this->assertNull(Project::defaultEditorial());
    }

    // ── Task di tipo Sviluppo e collegamento a Prompt (Blocco A) ────

    public function test_a_development_task_can_store_github_sync_fields(): void
    {
        $task = ProjectTask::factory()->create([
            'type' => ProjectTask::TYPE_DEVELOPMENT,
            'github_branch' => 'feature/esempio',
            'github_pr_number' => 42,
            'github_pr_state' => 'open',
            'github_checks_state' => 'success',
            'github_review_state' => 'approved',
            'github_synced_at' => now(),
        ]);

        $fresh = $task->fresh();
        $this->assertSame('feature/esempio', $fresh->github_branch);
        $this->assertSame(42, $fresh->github_pr_number);
        $this->assertSame('open', $fresh->github_pr_state);
        $this->assertSame('success', $fresh->github_checks_state);
        $this->assertSame('approved', $fresh->github_review_state);
        $this->assertNotNull($fresh->github_synced_at);
    }

    public function test_a_prompt_can_be_linked_to_a_task(): void
    {
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->create(['type' => ProjectTask::TYPE_DEVELOPMENT]);
        $prompt = ProjectPrompt::factory()->for($project)->create(['task_id' => $task->id]);

        $this->assertTrue($prompt->fresh()->task->is($task));
        $this->assertTrue($task->fresh()->prompts->contains($prompt));
    }
}
