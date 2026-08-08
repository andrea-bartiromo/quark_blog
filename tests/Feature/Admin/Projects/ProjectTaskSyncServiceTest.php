<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectTaskSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTaskSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $status, ?\Illuminate\Support\Carbon $publishedAt = null): Article
    {
        return Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }

    public function test_creating_a_publication_task_linked_to_a_draft_article_derives_draft_status(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create(['article_id' => $article->id]);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_DRAFT, $task->derived_status);
        $this->assertSame(ProjectTask::SOURCE_DERIVED, $task->status_source);
    }

    public function test_publishing_the_linked_article_moves_the_task_to_completed(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create(['article_id' => $article->id]);

        $article->update(['status' => Article::STATUS_PUBLISHED]);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_PUBLISHED, $task->derived_status);
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $task->manual_status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_scheduling_the_linked_article_moves_the_task_forward_but_not_backward(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create([
            'article_id' => $article->id,
            'manual_status' => ProjectTask::STATUS_IN_REVIEW,
        ]);

        // "scheduled" mappa su STATUS_TAKEN, che e' PRIMA di in_review nella
        // progressione: non deve far regredire il task.
        $article->update(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_SCHEDULED, $task->derived_status);
        $this->assertSame(ProjectTask::STATUS_IN_REVIEW, $task->manual_status);
    }

    public function test_blocked_task_is_never_auto_overwritten_by_derived_status(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create([
            'article_id' => $article->id,
            'manual_status' => ProjectTask::STATUS_BLOCKED,
        ]);

        $article->update(['status' => Article::STATUS_PUBLISHED]);

        $task->refresh();
        $this->assertSame(ProjectTask::STATUS_BLOCKED, $task->manual_status);
        // Lo stato derivato si aggiorna comunque (e' informativo), solo
        // manual_status resta intoccato.
        $this->assertSame(ProjectTask::DERIVED_PUBLISHED, $task->derived_status);
    }

    public function test_manual_override_prevents_any_automatic_status_change(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create([
            'article_id' => $article->id,
            'manual_status' => ProjectTask::STATUS_TODO,
            'manual_override' => true,
        ]);

        $article->update(['status' => Article::STATUS_PUBLISHED]);

        $task->refresh();
        $this->assertSame(ProjectTask::STATUS_TODO, $task->manual_status);
    }

    public function test_deleted_article_marks_linked_task_as_invalid_link_without_deleting_it(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create(['article_id' => $article->id]);

        $article->delete();

        $task->refresh();
        $this->assertDatabaseHas('project_tasks', ['id' => $task->id]);
        $this->assertSame(ProjectTask::DERIVED_INVALID_LINK, $task->derived_status);
        $this->assertSame($article->id, $task->article_id);
    }

    public function test_non_publication_tasks_are_never_touched_by_the_sync_service(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->create([
            'type' => ProjectTask::TYPE_TASK,
            'article_id' => $article->id,
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $article->update(['status' => Article::STATUS_PUBLISHED]);

        $task->refresh();
        $this->assertNull($task->derived_status);
        $this->assertSame(ProjectTask::SOURCE_MANUAL, $task->status_source);
        $this->assertSame(ProjectTask::STATUS_TODO, $task->manual_status);
    }

    public function test_sync_all_reports_updated_and_skipped_counts(): void
    {
        $draftArticle = $this->article(Article::STATUS_DRAFT);
        $publishedArticle = $this->article(Article::STATUS_PUBLISHED);

        ProjectTask::factory()->publication()->create(['article_id' => $draftArticle->id]);
        ProjectTask::factory()->publication()->create(['article_id' => $publishedArticle->id]);

        // I task si sincronizzano gia' alla creazione (hook su ProjectTask
        // e su Article): una seconda passata non trova nulla da cambiare.
        $result = app(ProjectTaskSyncService::class)->syncAll();

        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $result['skipped']);
    }

    public function test_artisan_command_runs_successfully_and_is_idempotent(): void
    {
        $article = $this->article(Article::STATUS_PUBLISHED);
        ProjectTask::factory()->publication()->create(['article_id' => $article->id]);

        $this->artisan('projects:sync-derived-statuses')->assertExitCode(0);
        $this->artisan('projects:sync-derived-statuses')->assertExitCode(0);
    }

    // ── Audit 0, gap #3: Cronologia sul completamento automatico ─────

    public function test_publishing_the_linked_article_records_a_single_system_activity_log_entry(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create([
            'article_id' => $article->id,
            'title' => 'Pubblicazione capitolo Enigma',
        ]);

        $article->update(['status' => Article::STATUS_PUBLISHED]);

        $task->refresh();
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $task->manual_status);

        $this->assertSame(1, ProjectActivityLog::where('subject_type', 'project_task')->where('subject_id', $task->id)->count());
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $task->project_id,
            'subject_type' => 'project_task',
            'subject_id' => $task->id,
            'subject_title' => 'Pubblicazione capitolo Enigma',
            'action' => 'Articolo pubblicato — attività completata automaticamente',
            'source' => ProjectActivityLog::SOURCE_SYSTEM,
            'user_id' => null,
        ]);
    }

    public function test_repeated_syncs_of_the_same_published_article_do_not_duplicate_the_log_entry(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create(['article_id' => $article->id]);
        $service = app(ProjectTaskSyncService::class);

        $article->update(['status' => Article::STATUS_PUBLISHED]);
        $task->refresh();

        // Tre modi diversi in cui una nuova sincronizzazione può ripetersi
        // sullo stesso stato ormai stabile: chiamata diretta al servizio,
        // syncAll() da comando schedulato, nuovo salvataggio dell'articolo
        // già pubblicato (che rifà scattare l'hook Article::saved).
        $service->syncTask($task);
        $service->syncAll();
        $article->save();

        $this->assertSame(1, ProjectActivityLog::where('subject_type', 'project_task')->where('subject_id', $task->id)->count());
    }

    public function test_no_log_is_recorded_when_the_task_was_already_completed_before_the_article_was_published(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create([
            'article_id' => $article->id,
            'manual_status' => ProjectTask::STATUS_COMPLETED,
        ]);

        $article->update(['status' => Article::STATUS_PUBLISHED]);

        $this->assertSame(0, ProjectActivityLog::where('subject_type', 'project_task')->where('subject_id', $task->id)->count());
    }

    public function test_no_log_is_recorded_when_the_linked_task_is_blocked_suspended_or_cancelled(): void
    {
        foreach ([ProjectTask::STATUS_BLOCKED, ProjectTask::STATUS_SUSPENDED, ProjectTask::STATUS_CANCELLED] as $status) {
            $article = $this->article(Article::STATUS_DRAFT);
            ProjectTask::factory()->publication()->create([
                'article_id' => $article->id,
                'manual_status' => $status,
            ]);

            $article->update(['status' => Article::STATUS_PUBLISHED]);
        }

        $this->assertSame(0, ProjectActivityLog::where('subject_type', 'project_task')->count());
    }

    public function test_no_log_is_recorded_when_manual_override_prevents_the_completion(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create([
            'article_id' => $article->id,
            'manual_status' => ProjectTask::STATUS_TODO,
            'manual_override' => true,
        ]);

        $article->update(['status' => Article::STATUS_PUBLISHED]);

        $this->assertSame(0, ProjectActivityLog::where('subject_type', 'project_task')->where('subject_id', $task->id)->count());
    }

    public function test_no_log_is_recorded_for_intermediate_draft_review_or_scheduled_transitions(): void
    {
        $article = $this->article(Article::STATUS_DRAFT);
        $task = ProjectTask::factory()->publication()->create(['article_id' => $article->id]);

        $article->update(['status' => Article::STATUS_REVIEW]);
        $article->update(['status' => Article::STATUS_SCHEDULED, 'published_at' => now()->addDay()]);

        $this->assertSame(0, ProjectActivityLog::where('subject_type', 'project_task')->where('subject_id', $task->id)->count());
    }
}
