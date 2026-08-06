<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ProjectTask;

/**
 * Unico punto in cui vive la regola "stato derivato" per i task di tipo
 * Pubblicazione: se un task ha un articolo collegato, il suo stato riflette
 * quello editoriale dell'articolo, a meno che l'utente non abbia attivato
 * manual_override. Invarianti:
 *   - Bloccato/Sospeso/Annullato non vengono MAI sovrascritti automaticamente;
 *   - la progressione todo -> taken -> in_progress -> in_review -> completed
 *     non torna mai indietro, tranne "published" che forza sempre completed.
 */
class ProjectTaskSyncService
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
        ProjectTask::DERIVED_DRAFT => ProjectTask::STATUS_TODO,
        ProjectTask::DERIVED_IN_REVIEW => ProjectTask::STATUS_IN_REVIEW,
        ProjectTask::DERIVED_SCHEDULED => ProjectTask::STATUS_TAKEN,
        ProjectTask::DERIVED_PUBLISHED => ProjectTask::STATUS_COMPLETED,
    ];

    private const NEVER_OVERWRITE = [
        ProjectTask::STATUS_BLOCKED,
        ProjectTask::STATUS_SUSPENDED,
        ProjectTask::STATUS_CANCELLED,
    ];

    public function syncForArticle(Article $article): void
    {
        ProjectTask::query()
            ->publicationType()
            ->where('article_id', $article->id)
            ->each(fn (ProjectTask $task) => $this->syncTask($task));
    }

    public function syncTask(ProjectTask $task): bool
    {
        if ($task->type !== ProjectTask::TYPE_PUBLICATION || $task->article_id === null) {
            return false;
        }

        $article = Article::find($task->article_id);

        if (! $article) {
            return $this->applyDerivedStatus($task, ProjectTask::DERIVED_INVALID_LINK);
        }

        return $this->applyDerivedStatus($task, $this->derivedStatusFor($article));
    }

    public function syncAll(): array
    {
        $updated = 0;
        $skipped = 0;

        ProjectTask::query()->publicationType()->whereNotNull('article_id')->each(function (ProjectTask $task) use (&$updated, &$skipped) {
            $this->syncTask($task) ? $updated++ : $skipped++;
        });

        return ['updated' => $updated, 'skipped' => $skipped];
    }

    public function invalidateForDeletedArticle(int $articleId): void
    {
        ProjectTask::query()
            ->publicationType()
            ->where('article_id', $articleId)
            ->each(fn (ProjectTask $task) => $this->applyDerivedStatus($task, ProjectTask::DERIVED_INVALID_LINK));
    }

    private function derivedStatusFor(Article $article): string
    {
        return match ($article->status) {
            Article::STATUS_DRAFT, Article::STATUS_REVIEW => ProjectTask::DERIVED_DRAFT,
            Article::STATUS_SCHEDULED => ProjectTask::DERIVED_SCHEDULED,
            Article::STATUS_PUBLISHED => ProjectTask::DERIVED_PUBLISHED,
            default => ProjectTask::DERIVED_DRAFT,
        };
    }

    private function applyDerivedStatus(ProjectTask $task, string $derivedStatus): bool
    {
        if (self::$applying) {
            return false;
        }

        $target = $this->resolveManualStatus($task, $derivedStatus);

        $changed = $task->derived_status !== $derivedStatus
            || $task->status_source !== ProjectTask::SOURCE_DERIVED
            || ($target !== null && $task->manual_status !== $target);

        if (! $changed) {
            return false;
        }

        self::$applying = true;

        try {
            $task->derived_status = $derivedStatus;
            $task->status_source = ProjectTask::SOURCE_DERIVED;

            if ($target !== null) {
                $task->manual_status = $target;
            }

            if ($derivedStatus === ProjectTask::DERIVED_PUBLISHED) {
                $task->completed_at ??= now();
            }

            $task->save();
        } finally {
            self::$applying = false;
        }

        return true;
    }

    /**
     * Calcola il manual_status da riflettere per lo stato derivato dato,
     * rispettando le due invarianti: mai sovrascrivere Bloccato/Sospeso/
     * Annullato, mai regredire lungo la progressione (tranne "published",
     * che forza sempre il completamento).
     */
    private function resolveManualStatus(ProjectTask $task, string $derivedStatus): ?string
    {
        if ($task->manual_override) {
            return null;
        }

        if (in_array($task->manual_status, self::NEVER_OVERWRITE, true)) {
            return null;
        }

        if ($derivedStatus === ProjectTask::DERIVED_INVALID_LINK) {
            return null;
        }

        $target = self::DERIVED_TARGET[$derivedStatus] ?? null;

        if ($target === null) {
            return null;
        }

        if ($derivedStatus === ProjectTask::DERIVED_PUBLISHED) {
            return ProjectTask::STATUS_COMPLETED;
        }

        $currentRank = self::PROGRESSION[$task->manual_status] ?? 0;
        $targetRank = self::PROGRESSION[$target] ?? 0;

        return $targetRank > $currentRank ? $target : null;
    }
}
