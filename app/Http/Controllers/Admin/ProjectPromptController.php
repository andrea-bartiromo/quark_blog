<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectPromptRequest;
use App\Http\Requests\Admin\UpdateProjectPromptRequest;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectPrompt;
use Illuminate\Support\Facades\DB;

class ProjectPromptController extends Controller
{
    public function create(Project $project)
    {
        return view('admin.projects.prompt-form', [
            'project' => $project,
            'prompt' => new ProjectPrompt(['project_id' => $project->id]),
            'taskOptions' => $this->taskOptions($project),
        ]);
    }

    public function store(StoreProjectPromptRequest $request, Project $project)
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $prompt = ProjectPrompt::create($data);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'prompt',
            subjectId: $prompt->id,
            subjectTitle: $prompt->title,
            action: 'Prompt creato',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts'])->with('success', 'Prompt creato.');
    }

    public function edit(Project $project, ProjectPrompt $prompt)
    {
        return view('admin.projects.prompt-form', [
            'project' => $project,
            'prompt' => $prompt,
            'taskOptions' => $this->taskOptions($project),
        ]);
    }

    public function update(UpdateProjectPromptRequest $request, Project $project, ProjectPrompt $prompt)
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $prompt->update($data);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'prompt',
            subjectId: $prompt->id,
            subjectTitle: $prompt->title,
            action: 'Prompt aggiornato',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts'])->with('success', 'Prompt aggiornato.');
    }

    public function duplicate(Project $project, ProjectPrompt $prompt)
    {
        $copy = $prompt->replicate();
        $copy->title = $prompt->title.' (copia)';
        $copy->status = ProjectPrompt::STATUS_DRAFT;
        $copy->used_at = null;
        $copy->outcome = null;
        $copy->created_by = auth()->id();
        $copy->updated_by = auth()->id();
        $copy->save();

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'prompt',
            subjectId: $copy->id,
            subjectTitle: $copy->title,
            action: 'Prompt duplicato da «'.$prompt->title.'»',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts'])->with('success', 'Prompt duplicato.');
    }

    public function destroy(Project $project, ProjectPrompt $prompt)
    {
        // Vedi commento in ProjectTaskController::destroy(): stessa ragione
        // per la transazione (nessuna FK reale su subject_id, la entry
        // sopravvive comunque — la transazione garantisce solo che log e
        // delete non possano divergere).
        DB::transaction(function () use ($project, $prompt) {
            ProjectActivityLog::record(
                project: $project,
                subjectType: 'prompt',
                subjectId: $prompt->id,
                subjectTitle: $prompt->title,
                action: 'Prompt eliminato',
                userId: auth()->id(),
            );

            $prompt->delete();
        });

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'prompts'])->with('success', 'Prompt eliminato.');
    }

    private function taskOptions(Project $project)
    {
        return $project->tasks()->orderBy('title')->get(['id', 'title']);
    }
}
