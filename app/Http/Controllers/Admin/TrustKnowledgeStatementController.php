<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTrustKnowledgeStatementRequest;
use App\Http\Requests\Admin\UpdateTrustKnowledgeStatementRequest;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\TrustKnowledgeStatement;
use Illuminate\View\View;

/**
 * Cantiere 38 (programma "100 cantieri Kairus"): CRUD interno del modello
 * "Cosa sappiamo davvero" — solo `/admin`, mai una route pubblica (si
 * legga il docblock di TrustKnowledgeStatement). Nessuna pubblicazione,
 * nessun gate, nessuna anteprima: quelle restano esplicitamente ai
 * cantieri successivi (40-44).
 *
 * Cantiere 39: validazione estratta in
 * Store/UpdateTrustKnowledgeStatementRequest — stessa convenzione già
 * stabilita nel resto del pannello admin (vedi StoreArticleRequest).
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

    public function store(StoreTrustKnowledgeStatementRequest $request)
    {
        $data = $request->validated();
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

    public function update(UpdateTrustKnowledgeStatementRequest $request, TrustKnowledgeStatement $trustKnowledgeStatement)
    {
        $trustKnowledgeStatement->update($request->validated());

        return redirect()->route('admin.trust-knowledge.index')->with('success', 'Voce aggiornata.');
    }

    public function destroy(TrustKnowledgeStatement $trustKnowledgeStatement)
    {
        $trustKnowledgeStatement->delete();

        return redirect()->route('admin.trust-knowledge.index')->with('success', 'Voce eliminata.');
    }
}
