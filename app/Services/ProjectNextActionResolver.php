<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;

/**
 * NextActionResolver v1 — versione deterministica di
 * Project::suggestedNextAction() che rispetta depends_on_task_id.
 *
 * Contratto pubblico ESISTENTE (Project::suggestedNextAction(), usato dal
 * form progetto) non è toccato: questo è un servizio nuovo, separato, non
 * ancora collegato ad alcuna UI o vista. Nessuna scrittura su DB — solo
 * lettura.
 *
 * Priorità (identiche a suggestedNextAction(), ma filtrate per
 * eleggibilità in tutte e tre):
 *   1. task scaduta ELEGGIBILE (due_date < oggi);
 *   2. task ELEGGIBILE in scadenza entro 7 giorni;
 *   3. prima task ELEGGIBILE "da avviare" (todo/taken), per sort_order poi
 *      created_at — stesso ordinamento di oggi.
 *
 * Una task è eleggibile se depends_on_task_id è null, oppure se la
 * dipendenza ha manual_status === STATUS_COMPLETED. Qualunque altro stato
 * della dipendenza (todo/taken/in_progress/in_review/blocked/suspended/
 * cancelled) la rende non eleggibile — nessuna eccezione inventata: è il
 * contratto minimo così come specificato, non una scelta di prodotto.
 *
 * Se esistono task "da avviare" ma NESSUNA è eleggibile (tutte bloccate da
 * dipendenze non completate), il risultato è KIND_PENDING_DEPENDENCIES —
 * mai un task inventato, mai un silenzio che nasconde lavoro bloccato.
 */
class ProjectNextActionResolver
{
    public const KIND_OVERDUE = 'overdue';

    public const KIND_DUE_SOON = 'due_soon';

    public const KIND_ELIGIBLE_NOT_STARTED = 'eligible_not_started';

    public const KIND_PENDING_DEPENDENCIES = 'pending_dependencies';

    public const KIND_NONE = 'none';

    public function resolve(Project $project): ProjectNextActionResult
    {
        $overdue = $project->tasks()->overdue()->eligible()->orderBy('due_date')->first();

        if ($overdue) {
            return ProjectNextActionResult::forTask($overdue, self::KIND_OVERDUE);
        }

        $dueSoon = $project->tasks()->dueSoon()->eligible()->orderBy('due_date')->first();

        if ($dueSoon) {
            return ProjectNextActionResult::forTask($dueSoon, self::KIND_DUE_SOON);
        }

        $notStarted = fn () => $project->tasks()->whereIn('manual_status', [ProjectTask::STATUS_TODO, ProjectTask::STATUS_TAKEN]);

        $eligible = $notStarted()->eligible()->orderBy('sort_order')->orderBy('created_at')->first();

        if ($eligible) {
            return ProjectNextActionResult::forTask($eligible, self::KIND_ELIGIBLE_NOT_STARTED);
        }

        $notStartedCount = $notStarted()->count();

        if ($notStartedCount > 0) {
            return ProjectNextActionResult::pendingDependencies($notStartedCount);
        }

        return ProjectNextActionResult::none();
    }
}
