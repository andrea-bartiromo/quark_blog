<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;

/**
 * Calcola l'avanzamento di un progetto con una regola esplicita e
 * deterministica — niente stime, niente euristiche:
 *
 *     avanzamento = round(100 × task completate / task non annullate)
 *
 * Un progetto senza task (al netto delle annullate) ha avanzamento 0.
 *
 * Usa manual_status, non derived_status/effectiveStatus(): è il campo che
 * sia il percorso manuale sia i sync automatici (ProjectTaskSyncService,
 * ProjectTaskGithubSyncService) tengono sempre allineato allo stato reale
 * della task quando la raggiungono. derived_status è un'etichetta
 * informativa per la UI (es. "published", "github_pr_merged"), non un
 * valore mai uguale a ProjectTask::STATUS_COMPLETED — usarlo qui
 * escluderebbe silenziosamente tutte le task sincronizzate dal conteggio.
 *
 * Non tocca mai operational_status (idea/pianificato/bloccato/...): resta
 * un giudizio umano esplicito, concettualmente distinto dalla percentuale
 * numerica di avanzamento.
 */
class ProjectProgressService
{
    public function recalculate(Project $project): int
    {
        $total = $project->tasks()->where('manual_status', '!=', ProjectTask::STATUS_CANCELLED)->count();

        $progress = $total === 0
            ? 0
            : (int) round(100 * $project->tasks()->where('manual_status', ProjectTask::STATUS_COMPLETED)->count() / $total);

        if ($project->progress !== $progress) {
            $project->forceFill(['progress' => $progress])->saveQuietly();
        }

        return $progress;
    }
}
