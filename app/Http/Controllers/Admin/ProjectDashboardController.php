<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use App\Models\ProjectTask;
use App\Services\ProjectAction\NextActionSuggestion;
use App\Services\ProjectAction\ProjectHealth;
use App\Services\ProjectAction\ProjectHealthResolver;

class ProjectDashboardController extends Controller
{
    /**
     * Pagina principale dell'Area Progettazione: solo liste, nessun grafico
     * decorativo. Sette liste — capire a colpo d'occhio cosa si sta facendo,
     * cosa e' bloccato, cosa deve avvenire dopo, cosa verra' pubblicato.
     */
    public function index()
    {
        $activeProjects = Project::active()->with('responsible')->orderByPrioritySeverity()->limit(10)->get();
        $blockedProjects = Project::blocked()->with('responsible')->orderByDesc('updated_at')->get();

        return view('admin.projects.dashboard', [
            // Correzione #2 approvata in revisione: ordina per severita'
            // reale (critical > high > medium > low) invece che
            // alfabeticamente — vedi Project::scopeOrderByPrioritySeverity().
            'activeProjects' => $activeProjects,
            'blockedProjects' => $blockedProjects,
            'dueSoonTasks' => ProjectTask::dueSoon()->with('project')->orderBy('due_date')->limit(10)->get(),
            'overdueTasks' => ProjectTask::overdue()->with('project')->orderBy('due_date')->limit(10)->get(),
            'upcomingPublications' => ProjectTask::publicationType()
                ->where('derived_status', ProjectTask::DERIVED_SCHEDULED)
                ->with(['project', 'article'])
                ->orderBy('due_date')
                ->limit(10)
                ->get(),
            'recentDocuments' => ProjectDocument::with('project')->orderByDesc('updated_at')->limit(10)->get(),
            'lastActivity' => ProjectActivityLog::with(['project', 'user'])->orderByDesc('created_at')->limit(15)->get(),
            'priorityNextActions' => Project::highPriority()->active()->whereNotNull('next_action')->with('responsible')->orderByPrioritySeverity()->limit(10)->get(),
            'attentionItems' => $this->attentionItems($activeProjects, $blockedProjects),
            // Esclude i dipendenti già in uno stato terminale (completata
            // o annullata): non sono più "in attesa" di niente, anche se
            // la loro dipendenza non è mai stata completata — stessa
            // restrizione già applicata dal resolver v1 (task "da
            // avviare" = todo/taken) prima di valutare l'eleggibilità.
            'blockedByDependencyCount' => ProjectTask::query()
                ->whereNotNull('depends_on_task_id')
                ->whereNotIn('manual_status', [ProjectTask::STATUS_COMPLETED, ProjectTask::STATUS_CANCELLED])
                ->whereHas('dependsOn', fn ($q) => $q->where('manual_status', '!=', ProjectTask::STATUS_COMPLETED))
                ->count(),
        ]);
    }

    /**
     * Progetti candidati al calcolo di "Richiedono attenzione", PRIMA di
     * risolvere health/segnali per ciascuno — non solo il risultato finale
     * (8), anche il numero di ProjectHealthResolver::resolve() eseguiti
     * deve restare limitato. $activeProjects è già limitato in query
     * (limit 10), ma $blockedProjects no (mostra tutti i progetti bloccati
     * nella sua card dedicata): senza questo taglio, un database con molti
     * progetti bloccati risolverebbe la salute di ognuno — inclusa,
     * per un progetto con calendario, una riconciliazione completa
     * (Article::all()) — prima di scartare quasi tutti con take(8).
     */
    private const MAX_ATTENTION_CANDIDATES = 20;

    /**
     * FASE 3-4-6-7, missione Dashboard Automation V2: "cosa richiede
     * attenzione adesso" per un insieme DELIBERATAMENTE LIMITATO di
     * progetti — solo quelli già caricati sopra (attivi in evidenza +
     * bloccati), mai l'intera tabella, e comunque mai più di
     * MAX_ATTENTION_CANDIDATES prima di risolvere. Ogni progetto in
     * questo insieme costa un piccolo numero fisso di query (vedi
     * ProjectNextActionResolverV2) — limitato, non un N+1 sulla tabella
     * intera. Un solo resolve() per progetto: ProjectHealth::$signals è
     * già la lista completa e ordinata usata anche da
     * ProjectNextActionResolverV2::resolve() — richiamarlo a parte
     * ricalcolerebbe (e riquerierebbe) esattamente le stesse cose.
     * Verificato con un dataset realistico in
     * ProjectDashboardPerformanceTest.
     *
     * @return list<array{project: Project, health: ProjectHealth, suggestion: NextActionSuggestion}>
     */
    private function attentionItems($activeProjects, $blockedProjects): array
    {
        $healthResolver = app(ProjectHealthResolver::class);

        return $activeProjects->concat($blockedProjects)
            ->unique('id')
            ->take(self::MAX_ATTENTION_CANDIDATES)
            ->map(function (Project $project) use ($healthResolver) {
                $health = $healthResolver->resolve($project);

                return [
                    'project' => $project,
                    'health' => $health,
                    'suggestion' => $health->signals[0] ?? NextActionSuggestion::aligned(),
                ];
            })
            ->filter(fn (array $item) => $item['health']->level !== ProjectHealth::LEVEL_OK)
            ->sortBy(fn (array $item) => $item['suggestion']->severityRank())
            ->take(8)
            ->values()
            ->all();
    }
}
