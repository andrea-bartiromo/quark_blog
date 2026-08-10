<?php

namespace App\Services\ProjectAction;

use App\Models\Project;

/**
 * Calcola ProjectHealth per un progetto (FASE 6, missione Dashboard
 * Automation V2). Sola lettura: nessuna scrittura, nessuna cache, sempre
 * ricalcolato dallo stato corrente — vedi ProjectNextActionResolverV2.
 *
 * Mappatura esplicita (nessun'altra regola altrove):
 *   - stato editoriale esplicito "Bloccato" (decisione umana già presa)
 *     → sempre Bloccato, indipendentemente dai segnali;
 *   - almeno un segnale urgente → Bloccato;
 *   - almeno un segnale "attenzione" (nessuno urgente) → Attenzione;
 *   - nessun segnale, o solo segnali puramente informativi → OK.
 *
 * "Bloccato" qui significa "richiede un intervento prima di poter
 * proseguire", non necessariamente lo stesso fatto di
 * ProjectTask::isBlockedByDependency() su una singola task — una task
 * bloccata da una dipendenza non ancora completata è normale
 * amministrazione del lavoro (severità "attenzione" in
 * ProjectNextActionResolverV2), non di per sé un'emergenza per l'intero
 * progetto.
 */
class ProjectHealthResolver
{
    public function __construct(
        private readonly ProjectNextActionResolverV2 $nextActionResolver,
    ) {}

    public function resolve(Project $project): ProjectHealth
    {
        if ($project->operational_status === Project::STATUS_BLOCKED) {
            return new ProjectHealth(ProjectHealth::LEVEL_BLOCKED, $this->nextActionResolver->allSignals($project));
        }

        $signals = $this->nextActionResolver->allSignals($project);

        $level = match (true) {
            $this->hasSeverity($signals, NextActionSuggestion::SEVERITY_URGENT) => ProjectHealth::LEVEL_BLOCKED,
            $this->hasSeverity($signals, NextActionSuggestion::SEVERITY_ATTENTION) => ProjectHealth::LEVEL_ATTENTION,
            default => ProjectHealth::LEVEL_OK,
        };

        return new ProjectHealth($level, $signals);
    }

    /**
     * @param  list<NextActionSuggestion>  $signals
     */
    private function hasSeverity(array $signals, string $severity): bool
    {
        foreach ($signals as $signal) {
            if ($signal->severity === $severity) {
                return true;
            }
        }

        return false;
    }
}
