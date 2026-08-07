<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::query()
            ->with('responsible')
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

        $project = Project::create($data);

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

        $articleOptions = $activeTab === 'articles'
            ? Article::whereNotIn('id', $project->articles()->pluck('articles.id'))->orderByDesc('created_at')->limit(200)->get(['id', 'title'])
            : null;

        $activityLog = $activeTab === 'history'
            ? $project->activityLogs()->with('user')->orderByDesc('created_at')->orderByDesc('id')->get()
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
        ]);
    }

    public function edit(Project $project)
    {
        return view('admin.projects.form', [
            'project' => $project,
            'responsibleOptions' => $this->responsibleOptions(),
            'suggestedNextAction' => $project->suggestedNextAction(),
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

        $project->update($data);

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
