<?php

namespace App\Services\ProjectAction;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\Editorial\EditorialCalendarReconciliationEntry;
use App\Services\Editorial\EditorialCalendarReconciliationService;
use App\Services\ProjectNextActionResolver;

/**
 * Motore di suggerimenti "prossima azione" (FASE 3, missione Dashboard
 * Automation V2) — compone segnali da più fonti già esistenti e testate
 * (task eleggibili/scaduti — ProjectNextActionResolver v1; sync GitHub;
 * calendario editoriale — EditorialCalendarReconciliationService; scadenza
 * di progetto) in un unico elenco ordinato per severità, senza duplicare
 * nessuna delle logiche sottostanti.
 *
 * Sola lettura, sempre: nessun metodo qui scrive mai nulla. Il risultato è
 * sempre e solo un suggerimento — mai applicato automaticamente a un
 * progetto o a una task (vedi resolve(), che restituisce il segnale più
 * severo, mai un'azione già eseguita).
 *
 * Se nessun segnale è rilevante, resolve() restituisce sempre
 * NextActionSuggestion::aligned() — mai un suggerimento inventato solo per
 * riempire il campo.
 */
class ProjectNextActionResolverV2
{
    public function __construct(
        private readonly ProjectNextActionResolver $taskResolver,
        private readonly EditorialCalendarReconciliationService $reconciliationService,
    ) {}

    public function resolve(Project $project): NextActionSuggestion
    {
        $signals = $this->allSignals($project);

        return $signals[0] ?? NextActionSuggestion::aligned();
    }

    /**
     * Segnali secondari, nell'ordine di severità, esclusa la primaria già
     * restituita da resolve() — un piccolo insieme ordinato, mai un elenco
     * illimitato (limit di default 4, sufficiente per la UI senza
     * trasformarla in una lista infinita).
     *
     * @return list<NextActionSuggestion>
     */
    public function secondarySignals(Project $project, int $limit = 4): array
    {
        return array_slice($this->allSignals($project), 1, $limit);
    }

    /**
     * Ogni segnale rilevato per il progetto, ordinato per severità — vuoto
     * se non ce n'è nessuno (mai NextActionSuggestion::aligned() qui: quel
     * valore sentinella è una scelta di resolve(), non un fatto rilevato).
     * Usato anche da ProjectHealthResolver, per non ricalcolare due volte
     * la stessa raccolta di segnali.
     *
     * @return list<NextActionSuggestion>
     */
    public function allSignals(Project $project): array
    {
        return $this->sorted($this->collectSignals($project));
    }

    /**
     * @param  list<NextActionSuggestion>  $signals
     * @return list<NextActionSuggestion>
     */
    private function sorted(array $signals): array
    {
        usort($signals, fn (NextActionSuggestion $a, NextActionSuggestion $b) => $a->severityRank() <=> $b->severityRank());

        return $signals;
    }

    /**
     * Un progetto completato o annullato resta sempre "allineato": nessuna
     * amministrazione in corso è più attesa lì, anche se qualche task
     * residua risultasse ancora aperta o scaduta (un'incoerenza da
     * correggere eventualmente a mano, non un segnale operativo attivo da
     * inseguire — mai un'automazione rumorosa su progetti ormai chiusi).
     *
     * @return list<NextActionSuggestion>
     */
    private function collectSignals(Project $project): array
    {
        if (in_array($project->operational_status, [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED], true)) {
            return [];
        }

        return [
            ...$this->taskSignal($project),
            ...$this->githubSignals($project),
            ...$this->editorialSignals($project),
            ...$this->projectSignals($project),
        ];
    }

    /**
     * @return list<NextActionSuggestion>
     */
    private function taskSignal(Project $project): array
    {
        $result = $this->taskResolver->resolve($project);

        return match ($result->kind) {
            ProjectNextActionResolver::KIND_OVERDUE => [new NextActionSuggestion(
                code: 'task_overdue',
                label: "Attività scaduta: «{$result->task->title}» (scadeva il {$result->task->due_date->format('d/m/Y')})",
                rationale: 'La scadenza è già passata.',
                severity: NextActionSuggestion::SEVERITY_URGENT,
                source: 'task',
                requiresHumanDecision: true,
                entityType: 'project_task',
                entityId: $result->task->id,
            )],
            ProjectNextActionResolver::KIND_DUE_SOON => [new NextActionSuggestion(
                code: 'task_due_soon',
                label: "Attività in scadenza: «{$result->task->title}» il {$result->task->due_date->format('d/m/Y')}",
                rationale: null,
                severity: NextActionSuggestion::SEVERITY_ATTENTION,
                source: 'task',
                requiresHumanDecision: false,
                entityType: 'project_task',
                entityId: $result->task->id,
            )],
            ProjectNextActionResolver::KIND_ELIGIBLE_NOT_STARTED => [new NextActionSuggestion(
                code: 'task_not_started',
                label: "Avviare l'attività: «{$result->task->title}»",
                rationale: null,
                severity: NextActionSuggestion::SEVERITY_ATTENTION,
                source: 'task',
                requiresHumanDecision: false,
                entityType: 'project_task',
                entityId: $result->task->id,
            )],
            ProjectNextActionResolver::KIND_PENDING_DEPENDENCIES => [new NextActionSuggestion(
                code: 'task_blocked_by_dependency',
                label: "{$result->pendingDependencyCount} attività in attesa di una dipendenza non ancora completata",
                rationale: 'Nessuna di queste attività è avviabile finché la sua dipendenza non è completata.',
                severity: NextActionSuggestion::SEVERITY_ATTENTION,
                source: 'task',
                requiresHumanDecision: false,
            )],
            default => [],
        };
    }

    /**
     * Una PR mergiata dovrebbe portare la task a "Completata" (vedi
     * ProjectTaskGithubSyncService) — se non lo è, la ragione è sempre una
     * delle protezioni esplicite (bloccata/sospesa/annullata/
     * manual_override): un fatto reale che merita attenzione, mai
     * corretto qui in automatico.
     *
     * @return list<NextActionSuggestion>
     */
    private function githubSignals(Project $project): array
    {
        $inconsistent = $project->tasks()
            ->developmentType()
            ->where('derived_status', ProjectTask::DERIVED_GH_PR_MERGED)
            ->where('manual_status', '!=', ProjectTask::STATUS_COMPLETED)
            ->get();

        return $inconsistent->map(fn (ProjectTask $task) => new NextActionSuggestion(
            code: 'github_pr_merged_unconfirmed',
            label: "PR #{$task->github_pr_number} mergiata per «{$task->title}»: verificare il completamento",
            rationale: 'Lo stato manuale non riflette ancora la pull request mergiata (bloccata, sospesa, annullata o con override manuale).',
            severity: NextActionSuggestion::SEVERITY_URGENT,
            source: 'github',
            requiresHumanDecision: true,
            entityType: 'project_task',
            entityId: $task->id,
        ))->all();
    }

    /**
     * @return list<NextActionSuggestion>
     */
    private function editorialSignals(Project $project): array
    {
        if ($project->editorialCalendarDocument() === null) {
            return [];
        }

        $report = $this->reconciliationService->reconcile($project);

        $signals = [];

        if (($count = count($report->requiringReview())) > 0) {
            $signals[] = new NextActionSuggestion(
                code: 'editorial_requires_review',
                label: $count === 1
                    ? '1 voce del calendario richiede una verifica manuale (titolo ambiguo)'
                    : "{$count} voci del calendario richiedono una verifica manuale (titolo ambiguo)",
                rationale: 'Il titolo pianificato corrisponde a più articoli o a nessuno con sufficiente certezza — un collegamento automatico non è mai sicuro in questo caso.',
                severity: NextActionSuggestion::SEVERITY_URGENT,
                source: 'editorial_calendar',
                requiresHumanDecision: true,
            );
        }

        if (($missing = $report->missingArticles()) !== []) {
            $nearest = $this->earliestByPlannedDate($missing);
            $count = count($missing);

            $signals[] = new NextActionSuggestion(
                code: 'editorial_missing_article',
                label: $count === 1
                    ? "Prossimo articolo da scrivere: «{$nearest->entry()->title}» previsto per il {$nearest->entry()->date->format('d/m/Y')}"
                    : "{$count} voci del calendario non hanno ancora un articolo — la più vicina: «{$nearest->entry()->title}» prevista per il {$nearest->entry()->date->format('d/m/Y')}",
                rationale: null,
                severity: NextActionSuggestion::SEVERITY_ATTENTION,
                source: 'editorial_calendar',
                requiresHumanDecision: false,
            );
        }

        if (($count = count($report->dateDiscrepancies())) > 0) {
            $signals[] = new NextActionSuggestion(
                code: 'editorial_date_discrepancy',
                label: $count === 1
                    ? '1 articolo ha una data diversa da quella pianificata nel calendario'
                    : "{$count} articoli hanno una data diversa da quella pianificata nel calendario",
                rationale: null,
                severity: NextActionSuggestion::SEVERITY_INFO,
                source: 'editorial_calendar',
                requiresHumanDecision: false,
            );
        }

        if (($count = count($report->statusMismatches())) > 0) {
            $signals[] = new NextActionSuggestion(
                code: 'editorial_status_mismatch',
                label: $count === 1
                    ? '1 articolo ha uno stato diverso da quello dichiarato nel calendario'
                    : "{$count} articoli hanno uno stato diverso da quello dichiarato nel calendario",
                rationale: null,
                severity: NextActionSuggestion::SEVERITY_INFO,
                source: 'editorial_calendar',
                requiresHumanDecision: false,
            );
        }

        return $signals;
    }

    /**
     * @param  list<EditorialCalendarReconciliationEntry>  $entries
     */
    private function earliestByPlannedDate(array $entries): EditorialCalendarReconciliationEntry
    {
        usort($entries, fn ($a, $b) => $a->entry()->date <=> $b->entry()->date);

        return $entries[0];
    }

    /**
     * @return list<NextActionSuggestion>
     */
    private function projectSignals(Project $project): array
    {
        $signals = [];

        if ($project->due_date !== null) {
            if ($project->due_date->isPast()) {
                $signals[] = new NextActionSuggestion(
                    code: 'project_overdue',
                    label: "Il progetto è scaduto il {$project->due_date->format('d/m/Y')}",
                    rationale: null,
                    severity: NextActionSuggestion::SEVERITY_URGENT,
                    source: 'project',
                    requiresHumanDecision: true,
                    entityType: 'project',
                    entityId: $project->id,
                );
            } elseif ($project->due_date->lte(now()->addDays(7))) {
                $signals[] = new NextActionSuggestion(
                    code: 'project_due_soon',
                    label: "Il progetto scade il {$project->due_date->format('d/m/Y')}",
                    rationale: null,
                    severity: NextActionSuggestion::SEVERITY_ATTENTION,
                    source: 'project',
                    requiresHumanDecision: false,
                    entityType: 'project',
                    entityId: $project->id,
                );
            }
        }

        $totalOpenTasks = $project->tasks()->whereNotIn('manual_status', [ProjectTask::STATUS_COMPLETED, ProjectTask::STATUS_CANCELLED])->count();
        $totalTasks = $project->tasks()->where('manual_status', '!=', ProjectTask::STATUS_CANCELLED)->count();

        if ($totalTasks > 0 && $totalOpenTasks === 0) {
            $signals[] = new NextActionSuggestion(
                code: 'project_completable',
                label: 'Tutte le attività sono completate: il progetto può essere marcato come completato',
                rationale: null,
                severity: NextActionSuggestion::SEVERITY_ATTENTION,
                source: 'project',
                requiresHumanDecision: true,
                entityType: 'project',
                entityId: $project->id,
            );
        }

        return $signals;
    }
}
