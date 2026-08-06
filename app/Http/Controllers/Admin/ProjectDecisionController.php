<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectDecisionRequest;
use App\Http\Requests\Admin\UpdateProjectDecisionRequest;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDecision;

class ProjectDecisionController extends Controller
{
    public function create(Project $project)
    {
        return view('admin.projects.decision-form', [
            'project' => $project,
            'decision' => new ProjectDecision(['project_id' => $project->id]),
        ]);
    }

    public function store(StoreProjectDecisionRequest $request, Project $project)
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        if (in_array($data['status'], [ProjectDecision::STATUS_APPROVED, ProjectDecision::STATUS_REJECTED], true)) {
            $data['decided_at'] = now();
        }

        $decision = ProjectDecision::create($data);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'decision',
            subjectId: $decision->id,
            subjectTitle: $decision->title,
            action: 'Decisione registrata',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions'])->with('success', 'Decisione registrata.');
    }

    public function edit(Project $project, ProjectDecision $decision)
    {
        return view('admin.projects.decision-form', [
            'project' => $project,
            'decision' => $decision,
        ]);
    }

    public function update(UpdateProjectDecisionRequest $request, Project $project, ProjectDecision $decision)
    {
        $before = $decision->status;
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        if (
            in_array($data['status'], [ProjectDecision::STATUS_APPROVED, ProjectDecision::STATUS_REJECTED], true)
            && $decision->decided_at === null
        ) {
            $data['decided_at'] = now();
        }

        $decision->update($data);

        $action = $before !== $decision->status
            ? 'Decisione '.($decision->status === ProjectDecision::STATUS_APPROVED ? 'approvata' : 'aggiornata')
            : 'Decisione aggiornata';

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'decision',
            subjectId: $decision->id,
            subjectTitle: $decision->title,
            action: $action,
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions'])->with('success', 'Decisione aggiornata.');
    }

    public function destroy(Project $project, ProjectDecision $decision)
    {
        $decision->delete();

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'decisions'])->with('success', 'Decisione eliminata.');
    }
}
