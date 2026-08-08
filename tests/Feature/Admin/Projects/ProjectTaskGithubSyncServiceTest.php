<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectPrompt;
use App\Models\ProjectTask;
use App\Services\ProjectTaskGithubSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectTaskGithubSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?array $fakePr = null;

    private ?array $fakeCheckRuns = null;

    private ?array $fakeReviews = null;

    private bool $fakeBranchExists = true;

    private bool $fakeForceError = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.token' => 'fake-token',
            'services.github.repo' => 'andrea-bartiromo/quark_blog',
        ]);

        // Un solo resolver registrato per l'intero test: Http::fake(closure)
        // ACCUMULA i resolver invece di sostituirli, quindi registrarne uno
        // per ogni scenario (come fatto inizialmente) fa "vincere" sempre il
        // primo. Lo stato del fixture vive nelle proprietà dell'istanza,
        // aggiornate da fakeGithub()/forceGithubError() durante il test.
        Http::fake(function (Request $request) {
            if ($this->fakeForceError) {
                return Http::response(['message' => 'Internal Server Error'], 500);
            }

            $url = $request->url();

            if (str_contains($url, '/pulls?')) {
                return Http::response($this->fakePr ? [$this->fakePr] : [], 200);
            }

            if (str_contains($url, '/reviews')) {
                return Http::response($this->fakeReviews ?? [], 200);
            }

            if (str_contains($url, '/check-runs')) {
                return Http::response(['check_runs' => $this->fakeCheckRuns ?? []], 200);
            }

            if (str_contains($url, '/branches/')) {
                return $this->fakeBranchExists ? Http::response(['name' => 'x'], 200) : Http::response(['message' => 'Not Found'], 404);
            }

            return Http::response(['message' => 'unexpected'], 404);
        });
    }

    private function fakeGithub(?array $pr, ?array $checkRuns = null, ?array $reviews = null, bool $branchExists = true): void
    {
        $this->fakePr = $pr;
        $this->fakeCheckRuns = $checkRuns;
        $this->fakeReviews = $reviews;
        $this->fakeBranchExists = $branchExists;
    }

    private function forceGithubError(): void
    {
        $this->fakeForceError = true;
    }

    private function pr(array $overrides = []): array
    {
        return array_merge([
            'number' => 42,
            'state' => 'open',
            'draft' => false,
            'merged_at' => null,
            'head' => ['sha' => 'abc123'],
        ], $overrides);
    }

    public function test_a_task_without_a_branch_is_never_touched(): void
    {
        $task = ProjectTask::factory()->development()->create(['github_branch' => null]);

        $result = app(ProjectTaskGithubSyncService::class)->syncTask($task);

        $this->assertFalse($result);
        $this->assertNull($task->fresh()->github_synced_at);
    }

    public function test_a_non_development_task_is_never_touched_even_with_a_branch_set(): void
    {
        $task = ProjectTask::factory()->create(['type' => ProjectTask::TYPE_TASK, 'github_branch' => 'feature/x']);

        $result = app(ProjectTaskGithubSyncService::class)->syncTask($task);

        $this->assertFalse($result);
    }

    public function test_a_branch_with_no_pull_request_yet_moves_to_taken(): void
    {
        $this->fakeGithub(pr: null, branchExists: true);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_GH_BRANCH, $task->derived_status);
        $this->assertSame(ProjectTask::STATUS_TAKEN, $task->manual_status);
        $this->assertNotNull($task->github_synced_at);
    }

    public function test_an_open_pull_request_moves_the_task_to_in_review(): void
    {
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => 'success']], reviews: [['state' => 'APPROVED']]);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_GH_PR_OPEN, $task->derived_status);
        $this->assertSame(ProjectTask::STATUS_IN_REVIEW, $task->manual_status);
        $this->assertSame(42, $task->github_pr_number);
        $this->assertSame('open', $task->github_pr_state);
        $this->assertSame('success', $task->github_checks_state);
        $this->assertSame('approved', $task->github_review_state);
    }

    public function test_a_merged_pull_request_completes_the_task(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z', 'state' => 'closed']));
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $task->derived_status);
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $task->manual_status);
        $this->assertSame('merged', $task->github_pr_state);
        $this->assertNotNull($task->completed_at);
    }

    public function test_a_closed_unmerged_pull_request_does_not_advance_the_task(): void
    {
        $this->fakeGithub(pr: $this->pr(['state' => 'closed', 'merged_at' => null]));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_TAKEN,
        ]);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_GH_PR_CLOSED_UNMERGED, $task->derived_status);
        // Nessun avanzamento automatico: resta dove un umano lo aveva lasciato.
        $this->assertSame(ProjectTask::STATUS_TAKEN, $task->manual_status);
        $this->assertNull($task->completed_at);
    }

    public function test_status_never_regresses_when_pr_reopens_at_an_earlier_stage(): void
    {
        $this->fakeGithub(pr: $this->pr());
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_COMPLETED,
        ]);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_GH_PR_OPEN, $task->derived_status);
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $task->manual_status);
    }

    public function test_blocked_task_is_never_auto_overwritten(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_BLOCKED,
        ]);

        $task->refresh();
        $this->assertSame(ProjectTask::STATUS_BLOCKED, $task->manual_status);
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $task->derived_status);
    }

    public function test_manual_override_prevents_any_automatic_status_change(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_TODO,
            'manual_override' => true,
        ]);

        $task->refresh();
        $this->assertSame(ProjectTask::STATUS_TODO, $task->manual_status);
    }

    public function test_a_branch_that_no_longer_exists_and_has_no_pr_is_flagged_invalid_link(): void
    {
        $this->fakeGithub(pr: null, branchExists: false);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/cancellato']);

        $task->refresh();
        $this->assertSame(ProjectTask::DERIVED_INVALID_LINK, $task->derived_status);
    }

    public function test_merge_records_a_system_activity_log_on_the_project(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->development()->create(['github_branch' => 'feature/x', 'title' => 'Task di prova']);

        $log = ProjectActivityLog::where('project_id', $project->id)->where('subject_type', 'project_task')->first();

        $this->assertNotNull($log);
        $this->assertSame(ProjectActivityLog::SOURCE_SYSTEM, $log->source);
        $this->assertNull($log->user_id);
        $this->assertStringContainsString('#42', $log->action);
    }

    public function test_merge_completes_a_linked_prompt_without_overwriting_existing_outcome(): void
    {
        // Flusso realistico: il prompt viene collegato alla task PRIMA che
        // la PR venga mergiata (per avviare l'agente); solo dopo arriva il
        // merge, che deve completare il prompt già esistente.
        $this->fakeGithub(pr: $this->pr());
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->development()->create(['github_branch' => 'feature/x']);
        $prompt = ProjectPrompt::factory()->for($project)->create([
            'task_id' => $task->id,
            'status' => ProjectPrompt::STATUS_USED,
            'outcome' => 'Nota già scritta a mano.',
        ]);

        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $prompt->refresh();
        $this->assertSame(ProjectPrompt::STATUS_COMPLETED, $prompt->status);
        $this->assertSame('Nota già scritta a mano.', $prompt->outcome);
        $this->assertNotNull($prompt->used_at);
    }

    public function test_merge_is_only_logged_once_across_repeated_syncs(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $project = Project::factory()->create();
        $task = ProjectTask::factory()->for($project)->development()->create(['github_branch' => 'feature/x']);

        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $count = ProjectActivityLog::where('project_id', $project->id)->where('subject_type', 'project_task')->count();
        $this->assertSame(1, $count);
    }

    public function test_github_unreachable_does_not_throw_and_leaves_state_untouched(): void
    {
        $this->forceGithubError();
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $task->refresh();
        $this->assertSame(ProjectTask::STATUS_TODO, $task->manual_status);
        $this->assertNull($task->derived_status);
        $this->assertNull($task->github_synced_at);
    }

    public function test_no_token_configured_skips_sync_without_error(): void
    {
        config(['services.github.token' => null]);

        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        Http::assertNothingSent();
        $this->assertNull($task->fresh()->derived_status);
    }

    public function test_sync_all_reports_updated_and_skipped_counts(): void
    {
        $this->fakeGithub(pr: null, branchExists: true);
        ProjectTask::factory()->development()->create(['github_branch' => 'feature/a']);
        ProjectTask::factory()->development()->create(['github_branch' => 'feature/b']);

        // Già sincronizzati alla creazione (hook su ProjectTask): un secondo
        // giro non trova nulla da cambiare.
        $result = app(ProjectTaskGithubSyncService::class)->syncAll();

        $this->assertSame(0, $result['updated']);
        $this->assertSame(2, $result['skipped']);
    }

    public function test_re_syncing_with_no_changes_still_touches_the_synced_at_timestamp(): void
    {
        $this->fakeGithub(pr: null, branchExists: true);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        $firstSync = $task->fresh()->github_synced_at;

        $this->travel(1)->minutes();
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $this->assertTrue($task->fresh()->github_synced_at->gt($firstSync));
    }

    public function test_check_run_conclusion_action_required_is_classified_as_failing(): void
    {
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => 'action_required']]);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        $this->assertSame('failing', $task->fresh()->github_checks_state);
    }

    public function test_a_new_commit_with_no_check_runs_yet_does_not_inherit_the_previous_commits_checks_state(): void
    {
        $this->fakeGithub(pr: $this->pr(['head' => ['sha' => 'abc123']]), checkRuns: [['conclusion' => 'failure']]);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame('failing', $task->fresh()->github_checks_state);

        // Nuovo commit pushato sullo stesso branch: SHA diverso, nessun check
        // ancora avviato per esso (risposta riuscita ma elenco vuoto) — non
        // deve ereditare lo stato del commit precedente.
        $this->fakeGithub(pr: $this->pr(['head' => ['sha' => 'def456']]), checkRuns: null);
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $this->assertNull($task->fresh()->github_checks_state);
    }

    // ── Hardening (PR #132): completed_at solo su transizione effettiva ──

    public function test_completed_at_is_set_when_a_normal_task_actually_completes(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_IN_REVIEW,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_completed_at_stays_null_when_a_blocked_task_merges(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_BLOCKED,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_BLOCKED, $fresh->manual_status);
        $this->assertNull($fresh->completed_at);
    }

    public function test_completed_at_stays_null_when_a_suspended_task_merges(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_SUSPENDED,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_SUSPENDED, $fresh->manual_status);
        $this->assertNull($fresh->completed_at);
    }

    public function test_completed_at_stays_null_when_a_cancelled_task_merges(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_CANCELLED,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_CANCELLED, $fresh->manual_status);
        $this->assertNull($fresh->completed_at);
    }

    public function test_completed_at_stays_null_when_manual_override_prevents_completion(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_TODO,
            'manual_override' => true,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_TODO, $fresh->manual_status);
        $this->assertNull($fresh->completed_at);
    }

    public function test_completed_at_already_set_is_never_reset_or_altered_by_a_later_sync(): void
    {
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_COMPLETED,
            'completed_at' => '2026-01-01 09:00:00',
        ]);
        $originalCompletedAt = $task->completed_at;

        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertTrue($fresh->completed_at->equalTo($originalCompletedAt));
    }

    public function test_completed_at_is_idempotent_across_repeated_syncs_after_completion(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $firstCompletedAt = $task->fresh()->completed_at;

        $this->travel(1)->hours();
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertTrue($fresh->completed_at->equalTo($firstCompletedAt));
    }
}
