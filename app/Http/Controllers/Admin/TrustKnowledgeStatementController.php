<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\TrustKnowledgeStatement;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Cantiere 38 (programma "100 cantieri Kairus"): CRUD interno del modello
 * "Cosa sappiamo davvero" — solo `/admin`, mai una route pubblica (si
 * legga il docblock di TrustKnowledgeStatement). Nessuna pubblicazione,
 * nessun gate, nessuna anteprima: quelle restano esplicitamente ai
 * cantieri successivi (40-44).
 */
class TrustKnowledgeStatementController extends Controller
{
    public function index(): View
    {
        $statements = TrustKnowledgeStatement::query()
            ->with(['concept:id,name', 'contentCluster:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('admin.trust-knowledge.index', ['statements' => $statements]);
    }

    public function create(): View
    {
        return view('admin.trust-knowledge.form', [
            'statement' => null,
            'concepts' => Concept::query()->orderBy('name')->get(['id', 'name']),
            'clusters' => ContentCluster::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        TrustKnowledgeStatement::create($data);

        return redirect()->route('admin.trust-knowledge.index')->with('success', 'Voce creata.');
    }

    public function edit(TrustKnowledgeStatement $trustKnowledgeStatement): View
    {
        return view('admin.trust-knowledge.form', [
            'statement' => $trustKnowledgeStatement,
            'concepts' => Concept::query()->orderBy('name')->get(['id', 'name']),
            'clusters' => ContentCluster::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, TrustKnowledgeStatement $trustKnowledgeStatement)
    {
        $trustKnowledgeStatement->update($this->validated($request));

        return redirect()->route('admin.trust-knowledge.index')->with('success', 'Voce aggiornata.');
    }

    public function destroy(TrustKnowledgeStatement $trustKnowledgeStatement)
    {
        $trustKnowledgeStatement->delete();

        return redirect()->route('admin.trust-knowledge.index')->with('success', 'Voce eliminata.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'domanda' => ['required', 'string', 'max:300'],
            'consenso' => ['required', 'string', 'max:8000'],
            'incertezza' => ['required', 'string', 'max:8000'],
            'cosa_manca' => ['nullable', 'string', 'max:8000'],
            'last_checked_at' => ['nullable', 'date'],
            'last_checked_by' => ['nullable', 'string', 'max:150'],
            'concept_id' => ['nullable', 'integer', 'exists:concepts,id'],
            'content_cluster_id' => ['nullable', 'integer', 'exists:content_clusters,id'],
        ]);
    }
}
