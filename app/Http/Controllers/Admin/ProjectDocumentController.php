<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectDocumentRequest;
use App\Http\Requests\Admin\UpdateProjectDocumentRequest;
use App\Models\Media;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use Illuminate\Http\Request;

class ProjectDocumentController extends Controller
{
    /**
     * Vista cross-progetto di tutti i documenti (nav "Documenti").
     */
    public function indexAll(Request $request)
    {
        $documents = ProjectDocument::query()
            ->with('project')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.projects.documents-index', [
            'documents' => $documents,
        ]);
    }

    /**
     * Passaggio di selezione progetto per "Nuovo documento" dalla vista
     * globale "Documenti": creare un documento richiede sempre un progetto,
     * quindi prima si sceglie quale.
     */
    public function createPickProject()
    {
        return view('admin.projects.documents-pick-project', [
            'projects' => Project::query()->orderByPrioritySeverity()->orderByDesc('updated_at')->get(),
        ]);
    }

    public function create(Project $project)
    {
        return view('admin.projects.document-form', [
            'project' => $project,
            'document' => new ProjectDocument(['project_id' => $project->id]),
            'mediaOptions' => $this->mediaOptions(),
        ]);
    }

    public function store(StoreProjectDocumentRequest $request, Project $project)
    {
        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['version'] = 1;
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $document = ProjectDocument::create($data);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'document',
            subjectId: $document->id,
            subjectTitle: $document->title,
            action: 'Documento creato',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'documents'])->with('success', 'Documento creato.');
    }

    public function edit(Project $project, ProjectDocument $document)
    {
        return view('admin.projects.document-form', [
            'project' => $project,
            'document' => $document,
            'mediaOptions' => $this->mediaOptions(),
        ]);
    }

    public function update(UpdateProjectDocumentRequest $request, Project $project, ProjectDocument $document)
    {
        $data = $request->validated();
        $data['version'] = $document->version + 1;
        $data['updated_by'] = auth()->id();

        $document->update($data);

        ProjectActivityLog::record(
            project: $project,
            subjectType: 'document',
            subjectId: $document->id,
            subjectTitle: $document->title,
            action: 'Documento aggiornato (v'.$document->version.')',
            userId: auth()->id(),
        );

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'documents'])->with('success', 'Documento aggiornato.');
    }

    public function destroy(Project $project, ProjectDocument $document)
    {
        $document->delete();

        return redirect()->route('admin.progettazione.projects.show', [$project, 'tab' => 'documents'])->with('success', 'Documento eliminato.');
    }

    private function mediaOptions()
    {
        return Media::orderByDesc('created_at')->limit(200)->get(['id', 'filename']);
    }
}
