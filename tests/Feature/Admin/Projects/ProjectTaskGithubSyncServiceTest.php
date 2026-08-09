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

    // Audit 0 — caratterizzazione: a differenza di $fakeForceError (che fa
    // fallire QUALUNQUE chiamata), questi due flag permettono di far
    // fallire selettivamente solo l'endpoint check-runs o solo quello
    // reviews, lasciando riuscire la ricerca PR — necessario per
    // caratterizzare i casi di "partial failure" della matrice FASE 3
    // (#2/#3/#4/#5), che altrimenti non sarebbero simulabili con il fixture
    // esistente (tutto o niente).
    private bool $failChecksEndpoint = false;

    private bool $failReviewEndpoint = false;

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
                if ($this->failReviewEndpoint) {
                    return Http::response(['message' => 'Internal Server Error'], 500);
                }

                return Http::response($this->fakeReviews ?? [], 200);
            }

            if (str_contains($url, '/check-runs')) {
                if ($this->failChecksEndpoint) {
                    return Http::response(['message' => 'Internal Server Error'], 500);
                }

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
        $this->failChecksEndpoint = false;
        $this->failReviewEndpoint = false;
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

    // ══════════════════════════════════════════════════════════════════
    // Audit 0 — caratterizzazione (PR tests-only, nessuna correzione):
    // partial failure, torn read, letture ripetute, concorrenza.
    // Ogni test descrive il comportamento ATTUALE, anche quando sorprendente
    // — vedi il report della PR per l'elenco delle anomalie da valutare in
    // una futura PR di hardening.
    // ══════════════════════════════════════════════════════════════════

    // Matrice #2: PR trovata, endpoint checks fallisce (review riesce).
    public function test_checks_endpoint_failure_leaves_checks_state_stale_but_still_persists_the_rest(): void
    {
        // Stato pregresso da un sync precedente riuscito, cosi' il fallback
        // "resta al valore precedente" ha davvero un valore da mostrare.
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => 'success']], reviews: [['state' => 'APPROVED']]);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame('success', $task->fresh()->github_checks_state);

        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']), reviews: [['state' => 'APPROVED']]);
        $this->failChecksEndpoint = true;
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        // Il fallimento è assorbito: nessuna eccezione, il resto della
        // sincronizzazione (classificazione PR, manual_status, review,
        // completed_at, synced_at) procede comunque sui dati disponibili.
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $fresh->derived_status);
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertSame('merged', $fresh->github_pr_state);
        $this->assertSame('approved', $fresh->github_review_state);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull($fresh->github_synced_at);
        // Il campo che dipendeva dalla chiamata fallita resta al valore
        // PRECEDENTE ("success", da prima del merge) — non null, non
        // "unknown": un valore reale ma ormai scaduto, indistinguibile nel
        // dato stesso da un valore appena confermato.
        $this->assertSame('success', $fresh->github_checks_state);
    }

    // Matrice #3/#5: PR trovata, endpoint review fallisce (checks riesce).
    public function test_review_endpoint_failure_leaves_review_state_stale_but_still_persists_the_rest(): void
    {
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => 'success']], reviews: [['state' => 'CHANGES_REQUESTED']]);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame('changes_requested', $task->fresh()->github_review_state);

        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']), checkRuns: [['conclusion' => 'success']]);
        $this->failReviewEndpoint = true;
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $fresh->derived_status);
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertSame('success', $fresh->github_checks_state);
        $this->assertNotNull($fresh->completed_at);
        $this->assertNotNull($fresh->github_synced_at);
        // Stesso principio: il task risulta "completato" mentre la review
        // mostrata resta "changes_requested" — un dato pre-merge ormai
        // scaduto, non un errore visibile.
        $this->assertSame('changes_requested', $fresh->github_review_state);
    }

    // Matrice #4: entrambi checks e review falliscono nello stesso giro —
    // il caso limite in cui NESSuno dei due dati accessori è aggiornato,
    // ma la sincronizzazione avanza comunque lo stato principale.
    public function test_both_checks_and_review_endpoints_failing_still_advances_status_with_both_states_stale(): void
    {
        // conclusion: null (non la stringa "pending", che non è un valore
        // reale dell'API GitHub) è come fetchChecksState() rappresenta un
        // check-run ancora in corso — vedi il ramo `in_array(null, ...)`.
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => null]], reviews: []);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame('pending', $task->fresh()->github_checks_state);
        $this->assertSame('none', $task->fresh()->github_review_state);

        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $this->failChecksEndpoint = true;
        $this->failReviewEndpoint = true;
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertSame('pending', $fresh->github_checks_state);
        $this->assertSame('none', $fresh->github_review_state);
        $this->assertNotNull($fresh->github_synced_at);
    }

    // Torn read #1: fetchReviewState() non lega mai la review a uno SHA
    // specifico (a differenza di fetchChecksState($sha)) — GitHub restituisce
    // solo "l'ultima review sottomessa sulla PR", indipendentemente dal
    // commit su cui è stata data. Una review approvata su un commit vecchio
    // e checks falliti sul commit nuovo possono coesistere senza alcuna
    // segnalazione di incoerenza.
    public function test_review_state_is_never_correlated_to_a_specific_commit_unlike_checks_state(): void
    {
        $this->fakeGithub(
            pr: $this->pr(['head' => ['sha' => 'new-commit-sha']]),
            checkRuns: [['conclusion' => 'failure']],
            reviews: [['state' => 'APPROVED']], // in realtà data su un commit precedente, ma la fixture/il codice non lo distinguono
        );
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);

        $fresh = $task->fresh();
        // Persistito senza errori: checks "failure" e review "approved"
        // fianco a fianco, nessuna delle due invalida l'altra.
        $this->assertSame('failing', $fresh->github_checks_state);
        $this->assertSame('approved', $fresh->github_review_state);
    }

    // Torn read #2 (FASE 6, secondo proiettile): una PR mergiata con checks
    // provenienti da una fotografia precedente (chiamata fallita in questo
    // giro) — lo stato "completed" viene comunque scritto e persiste
    // insieme a un dato checks ormai non più verificato in questo sync.
    public function test_a_merge_synced_with_a_failed_checks_call_persists_completed_alongside_a_stale_checks_snapshot(): void
    {
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => null]]); // "pending", fotografia precedente
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame('pending', $task->fresh()->github_checks_state);

        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $this->failChecksEndpoint = true;
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $fresh->derived_status);
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertNotNull($fresh->completed_at);
        // Nessun campo distingue "checks confermati in questo sync" da
        // "checks ereditati da un sync precedente perché questo è fallito".
        $this->assertSame('pending', $fresh->github_checks_state);
    }

    // Matrice #6: la PR passa da open a merged tra due letture emulate
    // (due syncTask() successivi, come farebbero due giri di scheduler).
    public function test_pr_transitioning_from_open_to_merged_across_two_syncs_completes_the_task_on_the_second_run(): void
    {
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => 'success']], reviews: [['state' => 'APPROVED']]);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame(ProjectTask::STATUS_IN_REVIEW, $task->fresh()->manual_status);

        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:05:00Z']), checkRuns: [['conclusion' => 'success']], reviews: [['state' => 'APPROVED']]);
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $fresh->derived_status);
        $this->assertNotNull($fresh->completed_at);
        $this->assertSame(1, ProjectActivityLog::where('subject_id', $task->id)->where('subject_type', 'project_task')->count());
    }

    // Matrice #7: la PR passa da open a closed-unmerged tra due letture
    // emulate — nessun avanzamento automatico, richiede decisione umana.
    public function test_pr_transitioning_from_open_to_closed_unmerged_across_two_syncs_does_not_advance_the_task(): void
    {
        $this->fakeGithub(pr: $this->pr());
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame(ProjectTask::STATUS_IN_REVIEW, $task->fresh()->manual_status);

        $this->fakeGithub(pr: $this->pr(['state' => 'closed', 'merged_at' => null]));
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::DERIVED_GH_PR_CLOSED_UNMERGED, $fresh->derived_status);
        // manual_status resta esattamente dove un umano lo troverebbe
        // dall'ultimo sync utile: nessuna regressione, nessun avanzamento.
        $this->assertSame(ProjectTask::STATUS_IN_REVIEW, $fresh->manual_status);
        $this->assertNull($fresh->completed_at);
    }

    // Matrice #8: due sync consecutivi con risposte identiche — oltre a
    // github_synced_at, nessun altro campo deve cambiare, e nessuna entry
    // di Cronologia deve duplicarsi anche fuori dal caso "merge" (già
    // coperto da test_merge_is_only_logged_once_across_repeated_syncs).
    public function test_two_consecutive_syncs_with_identical_responses_change_nothing_but_synced_at(): void
    {
        $this->fakeGithub(pr: $this->pr(), checkRuns: [['conclusion' => 'success']], reviews: [['state' => 'APPROVED']]);
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $firstSyncedAt = $task->fresh()->github_synced_at;

        $this->travel(1)->minutes();
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_IN_REVIEW, $fresh->manual_status);
        $this->assertSame('success', $fresh->github_checks_state);
        $this->assertSame('approved', $fresh->github_review_state);
        $this->assertTrue($fresh->github_synced_at->gt($firstSyncedAt));
    }

    // Matrice #9: un secondo sync riporta uno stato PIÙ VECCHIO (PR di
    // nuovo "open") di quello già registrato (PR "merged"). Comportamento
    // sorprendente ma coerente col codice attuale: SOLO manual_status è
    // protetto dalla regressione (via PROGRESSION); derived_status e
    // github_pr_state vengono sovrascritti senza alcuna guardia, producendo
    // una riga incoerente (manual_status=completed, github_pr_state=open).
    // completed_at, una volta impostato, non viene mai azzerato.
    public function test_a_second_sync_reporting_an_older_pr_state_regresses_derived_fields_but_not_manual_status(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create(['github_branch' => 'feature/x']);
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $task->fresh()->manual_status);
        $completedAt = $task->fresh()->completed_at;

        // Risposta "vecchia" (es. cache/proxy GitHub, o una race con
        // un'altra chiamata) che mostra di nuovo la PR aperta.
        $this->fakeGithub(pr: $this->pr());
        app(ProjectTaskGithubSyncService::class)->syncTask($task->fresh());

        $fresh = $task->fresh();
        // Guardia rispettata: manual_status non regredisce.
        $this->assertSame(ProjectTask::STATUS_COMPLETED, $fresh->manual_status);
        // Nessuna guardia invece su questi tre campi: riflettono sempre
        // l'ultima risposta, anche se "più vecchia" della precedente.
        $this->assertSame(ProjectTask::DERIVED_GH_PR_OPEN, $fresh->derived_status);
        $this->assertSame('open', $fresh->github_pr_state);
        // completed_at non viene mai ripulito da una regressione successiva.
        $this->assertNotNull($fresh->completed_at);
        $this->assertTrue($fresh->completed_at->equalTo($completedAt));
    }

    // ── Hardening (PR #133): completed_at solo su transizione effettiva ──

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

    // Matrice #10 (suspended): stesso principio già verificato per blocked,
    // esplicitato anche per suspended con asserzioni complete.
    public function test_a_suspended_task_is_never_auto_overwritten_by_a_merged_pr(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_SUSPENDED,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_SUSPENDED, $fresh->manual_status);
        // derived_status/github_* si aggiornano comunque (sono informativi):
        // solo manual_status resta intoccato. completed_at resta null grazie
        // al fix #133 (gate su $target, non sul dato grezzo GitHub) — prima
        // di #133 si attivava comunque, indipendentemente da manual_status.
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $fresh->derived_status);
        $this->assertSame('merged', $fresh->github_pr_state);
        $this->assertNull($fresh->completed_at);
    }

    // Matrice #10 (cancelled).
    public function test_a_cancelled_task_is_never_auto_overwritten_by_a_merged_pr(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']));
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_CANCELLED,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_CANCELLED, $fresh->manual_status);
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $fresh->derived_status);
        // Stesso principio della task sospesa sopra: completed_at resta null
        // grazie al fix #133.
        $this->assertNull($fresh->completed_at);
    }

    // Matrice #11, versione esaustiva: manual_override blocca SOLO
    // manual_status — derived_status e i campi github_* continuano ad
    // aggiornarsi silenziosamente sotto al valore "congelato" mostrato
    // all'utente.
    public function test_manual_override_still_updates_derived_and_github_fields_even_though_manual_status_is_frozen(): void
    {
        $this->fakeGithub(pr: $this->pr(['merged_at' => '2026-08-07T10:00:00Z']), checkRuns: [['conclusion' => 'success']], reviews: [['state' => 'APPROVED']]);
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_TODO,
            'manual_override' => true,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_TODO, $fresh->manual_status);
        $this->assertSame(ProjectTask::DERIVED_GH_PR_MERGED, $fresh->derived_status);
        $this->assertSame('merged', $fresh->github_pr_state);
        $this->assertSame('success', $fresh->github_checks_state);
        $this->assertSame('approved', $fresh->github_review_state);
        $this->assertNotNull($fresh->github_synced_at);
        // Stesso principio già osservato su blocked/suspended/cancelled:
        // completed_at resta null grazie al fix #133, anche se manual_override
        // congela manual_status a "todo" e la task non risulta mai
        // "completed" agli occhi dell'utente.
        $this->assertNull($fresh->completed_at);
    }

    // Matrice #12, versione esaustiva su tutti i campi rilevanti.
    public function test_github_completely_unreachable_leaves_every_github_field_untouched(): void
    {
        $this->forceGithubError();
        $task = ProjectTask::factory()->development()->create([
            'github_branch' => 'feature/x',
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $fresh = $task->fresh();
        $this->assertSame(ProjectTask::STATUS_TODO, $fresh->manual_status);
        $this->assertNull($fresh->derived_status);
        $this->assertNull($fresh->github_pr_number);
        $this->assertNull($fresh->github_pr_state);
        $this->assertNull($fresh->github_checks_state);
        $this->assertNull($fresh->github_review_state);
        $this->assertNull($fresh->github_synced_at);
        $this->assertNull($fresh->completed_at);
        $this->assertSame(0, ProjectActivityLog::where('subject_id', $task->id)->where('subject_type', 'project_task')->count());
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
