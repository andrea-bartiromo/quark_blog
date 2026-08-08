<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectTaskRequest;
use App\Http\Requests\Admin\UpdateProjectTaskRequest;
use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectTaskController extends Controller
{
    /**
     * Vista cross-progetto di tutte le attività (nav "Attività progetti").
     */
    public function indexAll(Request $request)
    {
        $tasks = ProjectTask::query()
            ->with(['project', 'responsible', 'article'])
            ->when($request->filled('status'), fn ($q) => $q->where('manual_status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString();

        return view('admin.projects.tasks-index', [
            'tasks' => $tasks,
            'statusOptions' => ProjectTask::statusOptions(),
            'typeOptions' => ProjectTask::typeOptions(),
        ]);
    }

    /**
     * Passaggio di selezione progetto per "Nuova attività" dalle viste
     * globali (nav "Attività progetti", Calendario): creare un'attività
     * richiede sempre un progetto, quindi prima si sceglie quale.
     */
    public function createPickProject()
    {
        return view('admin.projects.tasks-pick-project', [
            'projects' => Project::query()->orderByPrioritySeverity()->orderByDesc('updated_at')->get(),
        ]);
    }

    public function create(Project $project)
    {
        return view('admin.projects.task-form', [
            'project' => $project,
            'task' => new ProjectTask(['project_id' => $project->id]),
            'responsibleOptions' => $this->responsibleOptions(),
            'articleOptions' => $this->articleOptions(),
        ]);
    }

    public function store(StoreProjectTaskRequest $request, Project $project)
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['status_source'] = ProjectTask::SOURCE_MANUAL;
        $data['sort_order'] = 0;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $task = ProjectTask::create($data);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'task',
            subjectId: $task->id,
            subjectTitle: $task->title,
            action: 'Attività creata',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks'])->with('success', 'Attività creata.');
    }

    public function edit(Project $project, ProjectTask $task)
    {
        return view('admin.projects.task-form', [
            'project' => $project,
            'task' => $task,
            'responsibleOptions' => $this->responsibleOptions(),
            'articleOptions' => $this->articleOptions(),
        ]);
    }

    public function update(UpdateProjectTaskRequest $request, Project $project, ProjectTask $task)
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $task->update($data);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'task',
            subjectId: $task->id,
            subjectTitle: $task->title,
            action: 'Attività aggiornata',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks'])->with('success', 'Attività aggiornata.');
    }

    public function destroy(Project $project, ProjectTask $task)
    {
        // Log e delete nella stessa transazione: nessuna FK reale lega
        // subject_id alla riga cancellata (project_activity_logs.subject_id
        // non ha vincolo verso project_tasks), quindi la entry sopravvive
        // comunque alla cancellazione — la transazione serve solo a
        // garantire che le due scritture non possano mai divergere (log
        // creato ma delete fallito, o viceversa).
        DB::transaction(function () use ($project, $task) {
            ProjectActivityLog::record(
                project: $project,
                subjectType: 'task',
                subjectId: $task->id,
                subjectTitle: $task->title,
                action: 'Attività eliminata',
                userId: auth()->id(),
            );

            $task->delete();
        });

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks'])->with('success', 'Attività eliminata.');
    }

    private function responsibleOptions()
    {
        return User::whereIn('role', ['editor', 'admin'])->orderBy('name')->get();
    }

    private function articleOptions()
    {
        return Article::orderByDesc('created_at')->limit(200)->get(['id', 'title']);
    }
}
