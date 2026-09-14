<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTrustKnowledgeStatementRequest;
use App\Http\Requests\Admin\UpdateTrustKnowledgeStatementRequest;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\TrustKnowledgeStatement;
use App\Services\Trust\TrustPilotPreviewMetricsService;
use Illuminate\View\View;

/**
 * Cantiere 38 (programma "100 cantieri Kairus"): CRUD interno del modello
 * "Cosa sappiamo davvero" — solo `/admin`, mai una route pubblica (si
 * legga il docblock di TrustKnowledgeStatement).
 *
 * Cantiere 39: validazione estratta in
 * Store/UpdateTrustKnowledgeStatementRequest — stessa convenzione già
 * stabilita nel resto del pannello admin (vedi StoreArticleRequest).
 *
 * Cantiere 40: preview() aggiunge un'anteprima di sola lettura, ANCORA
 * dentro il gruppo di rotte auth+editor — mai una route pubblica. Il
 * NO-GO B-45 (docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md) per "il
 * pilot manuale" resta in vigore; questo cantiere non tenta di
 * soddisfarne le condizioni mancanti (owner assegnato, contenuto reale
 * approvato). Gate di pubblicazione, componente accessibile e decisione
 * GO/NO-GO restano esplicitamente ai cantieri successivi (41-44).
 *
 * Cantiere 43: preview() registra un evento di visualizzazione
 * anonimo/aggregato (TrustPilotPreviewMetricsService::recordView()) —
 * rehearsal privacy-first della metrica B-44 "Visualizzazioni aggregate",
 * mai un identificativo di visitatore/sessione/utente. index() mostra il
 * conteggio aggregato per riga.
 */
class TrustKnowledgeStatementController extends Controller
{
    public function __construct(
        private readonly TrustPilotPreviewMetricsService $previewMetrics,
    ) {}

    public function index(): View
    {
        $statements = TrustKnowledgeStatement::query()
            ->with(['concept:id,name', 'contentCluster:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('admin.trust-knowledge.index', [
            'statements' => $statements,
            'previewMetrics' => $this->previewMetrics->aggregateViewsForMany(collect($statements->items())),
        ]);
    }

    public function create(): View
    {
        return view('admin.trust-knowledge.form', [
            'statement' => null,
            'concepts' => $this->conceptOptions(null),
            'clusters' => $this->clusterOptions(null),
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
            'concepts' => $this->conceptOptions($trustKnowledgeStatement->concept_id),
            'clusters' => $this->clusterOptions($trustKnowledgeStatement->content_cluster_id),
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

    public function preview(TrustKnowledgeStatement $trustKnowledgeStatement): View
    {
        $trustKnowledgeStatement->loadMissing(['concept:id,name', 'contentCluster:id,name']);
        $this->previewMetrics->recordView($trustKnowledgeStatement);

        return view('admin.trust-knowledge.preview', ['statement' => $trustKnowledgeStatement]);
    }

    /**
     * Cantiere 39, Codex (PR #596): le opzioni offerte nel form devono
     * combaciare con quello che StoreTrustKnowledgeStatementRequest
     * accetta davvero (solo Concept/Percorso attivi), altrimenti un
     * editor può selezionare un'opzione dal form e ricevere un errore di
     * validazione — frequente in pratica, dato che un Concept nasce
     * "bozza" per default. Un collegamento già esistente ma nel frattempo
     * archiviato resta comunque nell'elenco (mai far sparire dal form un
     * valore già salvato), marcato come tale nella vista.
     */
    private function conceptOptions(?int $currentId)
    {
        return Concept::query()
            ->where(function ($query) use ($currentId) {
                $query->where('status', Concept::STATUS_ACTIVE);
                if ($currentId !== null) {
                    $query->orWhere('id', $currentId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
    }

    private function clusterOptions(?int $currentId)
    {
        return ContentCluster::query()
            ->where(function ($query) use ($currentId) {
                $query->where('is_active', true);
                if ($currentId !== null) {
                    $query->orWhere('id', $currentId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }
}
