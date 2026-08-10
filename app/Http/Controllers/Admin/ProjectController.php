<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Editorial\EditorialCalendarNextActionResolver;
use App\Services\Editorial\EditorialCalendarProgress;
use App\Services\Editorial\EditorialCalendarReconciliationService;
use App\Services\ProjectAction\ProjectHealthResolver;
use App\Services\ProjectAction\ProjectNextActionResolverV2;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::query()
            ->with('responsible')
            // withCount, non un'ulteriore query per riga in vista (FASE 5,
            // missione Dashboard Automation V2): serve solo a distinguere
            // "0% fatto" da "nessuna attività ancora" nella colonna Avanzamento.
            ->withCount(['tasks as open_tasks_count' => fn ($q) => $q->where('manual_status', '!=', ProjectTask::STATUS_CANCELLED)])
            ->when($request->filled('status'), fn ($q) => $q->where('operational_status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderByPrioritySeverity()
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'statusOptions' => Project::statusOptions(),
            'typeOptions' => Project::typeOptions(),
        ]);
    }

    public function create()
    {
        return view('admin.projects.form', [
            'project' => new Project,
            'responsibleOptions' => $this->responsibleOptions(),
            'suggestedNextAction' => null,
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $data = $request->validated();
        // Una checkbox non spuntata non viene inviata dal browser: senza
        // questa coercizione esplicita is_default_editorial resterebbe
        // assente da $data invece di essere false.
        $data['is_default_editorial'] = $request->boolean('is_default_editorial');
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        // Transazione: lo spegnimento dell'eventuale predefinito precedente
        // (dentro Project::saving()) e la creazione di questa riga sono due
        // statement SQL distinti — senza transazione un'altra richiesta
        // potrebbe leggere lo stato a metà strada (nessun predefinito, o
        // temporaneamente due).
        $project = DB::transaction(fn () => Project::create($data));

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'project',
            subjectId: $project->id,
            subjectTitle: $project->title,
            action: 'Progetto creato',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', $project)->with('success', 'Progetto creato.');
    }

    public function show(Request $request, Project $project)
    {
        $project->load(['responsible', 'creator', 'editor']);

        $activeTab = $request->string('tab')->value() ?: 'overview';

        // Correzione #5 approvata in revisione: Documenti/Prompt/Decisioni
        // sono paginati (15 per pagina) invece di un ->get() illimitato, che
        // non avrebbe retto un progetto "vivo" con decine di voci accumulate.
        $documents = $activeTab === 'documents'
            ? $project->documents()->with('media')->orderByDesc('updated_at')->paginate(15)->withQueryString()
            : null;

        $prompts = $activeTab === 'prompts'
            ? $project->prompts()->with('article')->orderByDesc('updated_at')->paginate(15)->withQueryString()
            : null;

        $decisions = $activeTab === 'decisions'
            ? $project->decisions()->orderByDesc('created_at')->paginate(15)->withQueryString()
            : null;

        $linkedArticles = $activeTab === 'articles'
            ? $project->articles()->with('author')->orderByDesc('project_article.created_at')->get()
            : null;

        // Solo se il progetto ha un calendario marcato e solo per la
        // panoramica: il ricalcolo (parsing + query articoli) non è gratis,
        // niente lavoro extra sulle altre tab.
        $editorialProgress = null;
        if ($activeTab === 'overview' && $project->editorialCalendarDocument() !== null) {
            $editorialReport = app(EditorialCalendarReconciliationService::class)->reconcile($project);
            $editorialProgress = EditorialCalendarProgress::fromReport($editorialReport);
        }

        // FASE 3-4-6, missione Dashboard Automation V2: un solo progetto
        // qui, mai un elenco — nessun rischio N+1 a differenza della
        // dashboard (vedi ProjectDashboardController). Calcolato per ogni
        // tab, non solo la panoramica: il badge di stato in testata alla
        // pagina è condiviso da tutte le tab.
        $nextActionSuggestion = app(ProjectNextActionResolverV2::class)->resolve($project);
        $projectHealth = app(ProjectHealthResolver::class)->resolve($project);

        $articleOptions = $activeTab === 'articles'
            ? Article::whereNotIn('id', $project->articles()->pluck('articles.id'))->orderByDesc('created_at')->limit(200)->get(['id', 'title'])
            : null;

        // Stessa correzione #5 di Documenti/Prompt/Decisioni sopra: un
        // ->get() illimitato non avrebbe retto un progetto "vivo" con
        // centinaia di voci accumulate nel tempo (log di sola aggiunta,
        // mai potato). Nessun pageName dedicato: activeTab rende le tab
        // mutuamente esclusive per richiesta, quindi "page" non è mai
        // condiviso con un altro paginator nella stessa risposta, esattamente
        // come per documents/prompts/decisions.
        $activityLog = $activeTab === 'history'
            ? $project->activityLogs()->with('user')->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString()
            : null;

        return view('admin.projects.show', [
            'project' => $project,
            'activeTab' => $activeTab,
            'statusOptions' => Project::statusOptions(),
            'documents' => $documents,
            'prompts' => $prompts,
            'decisions' => $decisions,
            'linkedArticles' => $linkedArticles,
            'articleOptions' => $articleOptions,
            'activityLog' => $activityLog,
            'editorialProgress' => $editorialProgress,
            'nextActionSuggestion' => $nextActionSuggestion,
            'projectHealth' => $projectHealth,
        ]);
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', [
            'project' => $project,
            'responsibleOptions' => $this->responsibleOptions(),
            'suggestedNextAction' => app(EditorialCalendarNextActionResolver::class)->resolve($project),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $before = $project->only(['title', 'operational_status', 'priority']);

        $data = $request->validated();
        // Il form è a pagina intera e mostra sempre lo stato corrente della
        // checkbox: un invio senza il campo significa "non spuntata ora",
        // non "nessuna modifica" — coercizione esplicita necessaria perché
        // una checkbox non spuntata non viene inviata affatto dal browser.
        $data['is_default_editorial'] = $request->boolean('is_default_editorial');
        $data['updated_by'] = auth()->id();

        // Stessa ragione della transazione in store(): l'update di questa
        // riga e lo spegnimento dell'eventuale predefinito precedente devono
        // committare insieme, non come due statement osservabili separati.
        DB::transaction(fn () => $project->update($data));

        if ($before['title'] !== $project->title) {
            ProjectActivityLog::record(
                project: $project,
                subjectType: 'project',
                subjectId: $project->id,
                subjectTitle: $project->title,
                action: 'Titolo progetto cambiato da «'.$before['title'].'» a «'.$project->title.'»',
                userId: auth()->id(),
                oldValue: $before['title'],
                newValue: $project->title,
            );
        }

        if ($before['priority'] !== $project->priority) {
            ProjectActivityLog::record(
                project: $project,
                subjectType: 'project',
                subjectId: $project->id,
                subjectTitle: $project->title,
                action: 'Priorità progetto cambiata da «'.(Project::priorityOptions()[$before['priority']] ?? $before['priority']).'» a «'.(Project::priorityOptions()[$project->priority] ?? $project->priority).'»',
                userId: auth()->id(),
                oldValue: $before['priority'],
                newValue: $project->priority,
            );
        }

        if ($before['operational_status'] !== $project->operational_status) {
            ProjectActivityLog::record(
                project: $project,
                subjectType: 'project',
                subjectId: $project->id,
                subjectTitle: $project->title,
                action: 'Stato progetto cambiato da «'.(Project::statusOptions()[$before['operational_status']] ?? $before['operational_status']).'» a «'.(Project::statusOptions()[$project->operational_status] ?? $project->operational_status).'»',
                userId: auth()->id(),
            );
        }

        return redirect()->route('admin.progettazione.projects.show', $project)->with('success', 'Progetto aggiornato.');
    }

    /**
     * Correzione #3 approvata in revisione: consente di cambiare solo
     * operational_status senza attraversare il form di modifica completo.
     */
    public function updateStatus(Request $request, Project $project)
    {
        $validated = $request->validate([
            'operational_status' => ['required', Rule::in(array_keys(Project::statusOptions()))],
        ]);

        $before = $project->operational_status;
        $after = $validated['operational_status'];

        if ($before !== $after) {
            $project->update([
                'operational_status' => $after,
                'updated_by' => auth()->id(),
            ]);

            ProjectActivityLog::record(
                project: $project,
                subjectType: 'project',
                subjectId: $project->id,
                subjectTitle: $project->title,
                action: 'Stato progetto cambiato da «'.(Project::statusOptions()[$before] ?? $before).'» a «'.(Project::statusOptions()[$after] ?? $after).'»',
                userId: auth()->id(),
            );
        }

        return redirect()->route('admin.progettazione.projects.show', $project)->with('success', 'Stato aggiornato.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('admin.progettazione.projects.index')->with('success', 'Progetto eliminato.');
    }

    private function responsibleOptions()
    {
        return User::whereIn('role', ['editor', 'admin'])->orderBy('name')->get();
    }
}
