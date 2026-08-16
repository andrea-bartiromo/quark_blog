<?php

namespace App\Services;

use App\Models\ProjectActivityLog;
use App\Models\ProjectPrompt;
use App\Models\ProjectTask;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sync di sola lettura tra GitHub e i task di tipo Sviluppo — a specchio di
 * ProjectTaskSyncService (stesso schema di invarianti):
 *   - Bloccato/Sospeso/Annullato non vengono MAI sovrascritti automaticamente;
 *   - la progressione todo -> taken -> in_progress -> in_review -> completed
 *     non torna mai indietro, tranne il merge di una PR, che forza sempre
 *     "completed";
 *   - una PR chiusa senza merge NON avanza mai lo stato automaticamente:
 *     richiede sempre una decisione umana;
 *   - qualunque errore di rete/API non deve mai propagarsi: nessuna
 *     eccezione fatale, nessuna modifica quando lo stato reale non è noto.
 */
class ProjectTaskGithubSyncService
{
    private static bool $applying = false;

    private const PROGRESSION = [
        ProjectTask::STATUS_TODO => 0,
        ProjectTask::STATUS_TAKEN => 1,
        ProjectTask::STATUS_IN_PROGRESS => 2,
        ProjectTask::STATUS_IN_REVIEW => 3,
        ProjectTask::STATUS_COMPLETED => 4,
    ];

    private const DERIVED_TARGET = [
        ProjectTask::DERIVED_GH_BRANCH => ProjectTask::STATUS_TAKEN,
        ProjectTask::DERIVED_GH_PR_OPEN => ProjectTask::STATUS_IN_REVIEW,
        ProjectTask::DERIVED_GH_PR_MERGED => ProjectTask::STATUS_COMPLETED,
    ];

    private const NEVER_OVERWRITE = [
        ProjectTask::STATUS_BLOCKED,
        ProjectTask::STATUS_SUSPENDED,
        ProjectTask::STATUS_CANCELLED,
    ];

    private readonly ?string $token;

    private readonly ?string $repo;

    public function __construct(?string $token = null, ?string $repo = null)
    {
        $this->token = $token ?? config('services.github.token');
        $this->repo = $repo ?? config('services.github.repo');
    }

    public function syncTask(ProjectTask $task): bool
    {
        if (self::$applying) {
            return false;
        }

        if ($task->type !== ProjectTask::TYPE_DEVELOPMENT || blank($task->github_branch)) {
            return false;
        }

        if (blank($this->token) || blank($this->repo)) {
            return false;
        }

        try {
            $pr = $this->fetchPullRequestForBranch($task->github_branch);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        if ($pr === null) {
            return $this->syncWithoutPullRequest($task);
        }

        $derivedStatus = $this->classifyPr($pr);
        $prState = $this->prStateLabel($pr);

        $checksState = $task->github_checks_state;
        $reviewState = $task->github_review_state;

        try {
            $sha = $pr['head']['sha'] ?? null;
            if ($sha) {
                // Assegnazione diretta (non "?? $checksState"): una risposta
                // riuscita ma senza check-run per questo SHA è un dato reale
                // ("nessun check ancora avviato per il commit corrente"), non
                // un fallimento — riusare lo stato del task precedente
                // mostrerebbe il risultato dei check di uno SHA superato. Il
                // fallback allo stato precedente resta corretto solo
                // sull'eccezione sotto, dove lo stato reale è sconosciuto.
                $checksState = $this->fetchChecksState($sha);
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $reviewState = $this->fetchReviewState((int) $pr['number']) ?? $reviewState;
        } catch (Throwable $e) {
            report($e);
        }

        return $this->applySync($task, $derivedStatus, (int) $pr['number'], $prState, $checksState, $reviewState);
    }

    public function syncAll(): array
    {
        $updated = 0;
        $skipped = 0;

        ProjectTask::query()->developmentType()->whereNotNull('github_branch')->each(function (ProjectTask $task) use (&$updated, &$skipped) {
            $this->syncTask($task) ? $updated++ : $skipped++;
        });

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    private function syncWithoutPullRequest(ProjectTask $task): bool
    {
        try {
            $branchExists = $this->branchExists($task->github_branch);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        // Nessuna PR aperta: checks/review non si applicano più a questo
        // stato — azzerarli evita di mostrare dati di una PR precedente
        // ormai chiusa come se fossero ancora attuali.
        return $branchExists
            ? $this->applySync($task, ProjectTask::DERIVED_GH_BRANCH, null, 'open', null, null)
            : $this->applySync($task, ProjectTask::DERIVED_INVALID_LINK, null, null, null, null);
    }

    private function applySync(
        ProjectTask $task,
        string $derivedStatus,
        ?int $prNumber,
        ?string $prState,
        ?string $checksState,
        ?string $reviewState,
    ): bool {
        $wasAlreadyMerged = $task->derived_status === ProjectTask::DERIVED_GH_PR_MERGED;
        $target = $this->resolveManualStatus($task, $derivedStatus);

        $changed = $task->derived_status !== $derivedStatus
            || $task->status_source !== ProjectTask::SOURCE_DERIVED
            || $task->github_pr_number !== $prNumber
            || $task->github_pr_state !== $prState
            || $task->github_checks_state !== $checksState
            || $task->github_review_state !== $reviewState
            || ($target !== null && $task->manual_status !== $target);

        if (! $changed) {
            $task->forceFill(['github_synced_at' => now()])->saveQuietly();

            return false;
        }

        self::$applying = true;

        try {
            $task->derived_status = $derivedStatus;
            $task->status_source = ProjectTask::SOURCE_DERIVED;
            $task->github_pr_number = $prNumber;
            $task->github_pr_state = $prState;
            $task->github_checks_state = $checksState;
            $task->github_review_state = $reviewState;
            $task->github_synced_at = now();

            if ($target !== null) {
                $task->manual_status = $target;
            }

            // Condizionato su $target (già calcolato da resolveManualStatus()),
            // non sulla sola classificazione "merged": una PR mergiata non
            // significa che la task abbia davvero raggiunto STATUS_COMPLETED
            // — blocked/suspended/cancelled/manual_override fanno tornare
            // $target a null, e completed_at non deve attivarsi per loro.
            if ($target === ProjectTask::STATUS_COMPLETED) {
                $task->completed_at ??= now();
            }

            $task->save();

            if ($derivedStatus === ProjectTask::DERIVED_GH_PR_MERGED && ! $wasAlreadyMerged) {
                $this->handleMerge($task, $prNumber);
            }
        } finally {
            self::$applying = false;
        }

        return true;
    }

    /**
     * Al primo passaggio a "mergiata": registra l'evento in Cronologia come
     * azione di sistema e completa i Prompt collegati alla task, senza mai
     * sovrascrivere un outcome/used_at che l'utente ha già valorizzato.
     */
    private function handleMerge(ProjectTask $task, ?int $prNumber): void
    {
        ProjectActivityLog::record(
            project: $task->project,
            subjectType: 'project_task',
            subjectId: $task->id,
            subjectTitle: $task->title,
            action: "Pull request #{$prNumber} mergiata — attività completata automaticamente",
            userId: null,
            source: ProjectActivityLog::SOURCE_GITHUB,
        );

        $task->prompts()
            ->where('status', '!=', ProjectPrompt::STATUS_ARCHIVED)
            ->get()
            ->each(function (ProjectPrompt $prompt) use ($prNumber) {
                $dirty = false;

                if ($prompt->status !== ProjectPrompt::STATUS_COMPLETED) {
                    $prompt->status = ProjectPrompt::STATUS_COMPLETED;
                    $dirty = true;
                }

                if (blank($prompt->outcome)) {
                    $prompt->outcome = "Pull request #{$prNumber} mergiata su {$this->repo}.";
                    $dirty = true;
                }

                if (blank($prompt->used_at)) {
                    $prompt->used_at = now();
                    $dirty = true;
                }

                if ($dirty) {
                    $prompt->save();
                }
            });
    }

    private function resolveManualStatus(ProjectTask $task, string $derivedStatus): ?string
    {
        if ($task->manual_override) {
            return null;
        }

        if (in_array($task->manual_status, self::NEVER_OVERWRITE, true)) {
            return null;
        }

        $target = self::DERIVED_TARGET[$derivedStatus] ?? null;

        if ($target === null) {
            return null;
        }

        if ($derivedStatus === ProjectTask::DERIVED_GH_PR_MERGED) {
            return ProjectTask::STATUS_COMPLETED;
        }

        $currentRank = self::PROGRESSION[$task->manual_status] ?? 0;
        $targetRank = self::PROGRESSION[$target] ?? 0;

        return $targetRank > $currentRank ? $target : null;
    }

    private function classifyPr(array $pr): string
    {
        if (! empty($pr['merged_at'])) {
            return ProjectTask::DERIVED_GH_PR_MERGED;
        }

        if (($pr['state'] ?? null) === 'closed') {
            return ProjectTask::DERIVED_GH_PR_CLOSED_UNMERGED;
        }

        return ProjectTask::DERIVED_GH_PR_OPEN;
    }

    private function prStateLabel(array $pr): string
    {
        if (! empty($pr['merged_at'])) {
            return 'merged';
        }

        if (($pr['state'] ?? null) === 'closed') {
            return 'closed';
        }

        return ! empty($pr['draft']) ? 'draft' : 'open';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPullRequestForBranch(string $branch): ?array
    {
        [$owner] = explode('/', $this->repo, 2);

        $response = $this->client()->get("/repos/{$this->repo}/pulls", [
            'head' => "{$owner}:{$branch}",
            'state' => 'all',
            'sort' => 'created',
            'direction' => 'desc',
            'per_page' => 1,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("GitHub API: ricerca PR fallita per il branch «{$branch}» ({$response->status()}).");
        }

        $results = $response->json();

        return $results[0] ?? null;
    }

    private function branchExists(string $branch): bool
    {
        $response = $this->client()->get('/repos/'.$this->repo.'/branches/'.rawurlencode($branch));

        if ($response->status() === 404) {
            return false;
        }

        if (! $response->successful()) {
            throw new \RuntimeException("GitHub API: verifica branch fallita per «{$branch}» ({$response->status()}).");
        }

        return true;
    }

    private function fetchChecksState(string $sha): ?string
    {
        $response = $this->client()->get("/repos/{$this->repo}/commits/{$sha}/check-runs");

        if (! $response->successful()) {
            throw new \RuntimeException("GitHub API: lettura check-runs fallita per {$sha} ({$response->status()}).");
        }

        $runs = $response->json('check_runs') ?? [];

        if (empty($runs)) {
            return null;
        }

        $conclusions = array_column($runs, 'conclusion');

        if (in_array(null, $conclusions, true)) {
            return 'pending';
        }

        // 'action_required' e 'stale' segnalano un check che richiede
        // attenzione umana quanto un fallimento esplicito: classificarli
        // come "success" (comportamento del ramo default precedente)
        // nasconderebbe un problema reale in interfaccia.
        if (array_intersect(['failure', 'cancelled', 'timed_out', 'action_required', 'stale'], $conclusions)) {
            return 'failing';
        }

        return 'success';
    }

    private function fetchReviewState(int $prNumber): ?string
    {
        $response = $this->client()->get("/repos/{$this->repo}/pulls/{$prNumber}/reviews");

        if (! $response->successful()) {
            throw new \RuntimeException("GitHub API: lettura review fallita per la PR #{$prNumber} ({$response->status()}).");
        }

        $reviews = $response->json() ?? [];

        if (empty($reviews)) {
            return 'none';
        }

        $latest = end($reviews);

        return match ($latest['state'] ?? null) {
            'APPROVED' => 'approved',
            'CHANGES_REQUESTED' => 'changes_requested',
            'COMMENTED' => 'commented',
            default => 'pending',
        };
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->baseUrl('https://api.github.com')
            ->timeout(10);
    }
}
