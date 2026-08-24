<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Services\ContentGraph\ConceptAliasSyncService;
use App\Services\ContentGraph\ConceptDuplicateAuditService;
use App\Services\ContentGraph\ConceptMergeService;
use App\Services\ContentGraph\ConceptQuestionReadinessService;
use App\Services\ContentGraph\ContentGraphCoverageService;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Content Graph Admin V1 (docs/CONTENT_GRAPH_V1.md, PR #302): il minimo
 * CRUD utile per gestire Concept/Alias/ArticleConcept/ConceptQuestion senza
 * duplicare logica già presente in ContentGraphService — questo controller
 * resta un layer di orchestrazione HTTP, mai una seconda source of truth
 * per le regole di pubblicazione (discoverableConceptsForArticle()/
 * answerableQuestionsForConcept() restano l'unico contratto pubblico).
 *
 * Nessuna generazione automatica, nessun auto-approve, nessun auto-link:
 * ogni associazione Article↔Concept e ogni approvazione di una Question
 * è una scelta editoriale esplicita fatta qui.
 */
class ConceptController extends Controller
{
    public function __construct(
        private readonly ContentGraphService $contentGraph,
        private readonly ConceptAliasSyncService $aliasSync,
        private readonly ConceptDuplicateAuditService $duplicateAudit,
        private readonly ConceptMergeService $conceptMerge,
        private readonly ContentGraphCoverageService $coverage,
        private readonly ConceptQuestionReadinessService $questionReadiness,
    ) {}

    public function index()
    {
        $concepts = Concept::query()
            ->withCount(['aliases', 'articleLinks', 'questions'])
            ->orderBy('name')
            ->paginate(25);

        return view('admin.concepts.index', [
            'concepts' => $concepts,
            // Mission 17 — Duplicate Concept Detection: read-only audit,
            // mai un merge/eliminazione automatico — solo un segnale per
            // l'editor (stesso contratto di PercorsoCoverageAuditService).
            'duplicates' => $this->duplicateAudit->audit(),
            // Mission 19 — Coverage Metrics: solo numeri aggregati, nessun
            // elenco di singoli orfani (diagnostica separata).
            'coverage' => $this->coverage->summary(),
        ]);
    }

    public function create()
    {
        return view('admin.concepts.form', ['concept' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedConcept($request);
        $concept = Concept::create($data);

        return redirect()->route('admin.concepts.edit', $concept)->with('success', 'Concetto creato. Ora puoi aggiungere alias, articoli e domande.');
    }

    public function edit(Request $request, Concept $concept)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        // Mission 08 — Content Graph Questions V1 Editorial Workflow:
        // ordinato per sort_order/id, la stessa chiave usata da
        // ContentGraphService::questionsForConcept() — l'elenco admin non
        // deve mostrare un ordine diverso (l'insertion order grezzo) da
        // quello che l'ordinamento editoriale (sort_order) intende.
        $concept->load([
            'aliases',
            'questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')->with('targetArticle'),
        ]);
        $links = $this->contentGraph->articlesForConcept($concept);
        $linkedIds = $links->pluck('article_id');

        // Mission 08: unico punto che l'editor ha per capire, SENZA
        // ricalcolare la regola qui, se una domanda già salvata produce
        // davvero un link pubblico raggiungibile — riusa
        // ContentGraphService::answerableQuestionsForConcept() (lo stesso
        // contratto che vincolerà un futuro consumer pubblico), mai una
        // seconda implementazione della condizione nella vista.
        $answerableQuestionIds = $this->contentGraph
            ->answerableQuestionsForConcept($concept)
            ->pluck('id');

        // Mission 21 — Question Status Workflow V2: per ogni domanda
        // "Approvata" ma non raggiungibile, IL PERCHÉ — itemizzato dalle
        // stesse condizioni di answerableQuestionsForConcept(), mai
        // ricalcolate qui. Le domande già raggiungibili o non ancora
        // "Approvata" non hanno bisogno di questa spiegazione (il badge
        // binario esistente basta).
        $questionFindings = $concept->questions
            ->filter(fn ($question) => $question->status === ConceptQuestion::STATUS_APPROVED && ! $answerableQuestionIds->contains($question->id))
            ->mapWithKeys(fn ($question) => [$question->id => $this->questionReadiness->evaluate($question)['findings']]);

        $catalog = Article::query()
            ->select(['id', 'title', 'status', 'published_at', 'category'])
            ->when($linkedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedIds))
            ->when(! empty($filters['q']), fn ($query) => $query->where('title', 'like', '%'.$filters['q'].'%'))
            ->orderBy('title')
            ->paginate(20)
            ->withQueryString();

        return view('admin.concepts.form', [
            'concept' => $concept,
            'links' => $links,
            'catalog' => $catalog,
            'answerableQuestionIds' => $answerableQuestionIds,
            'questionFindings' => $questionFindings,
            // Mission 18 — Merge Workflow Foundation: quali altri concetti
            // ConceptDuplicateAuditService (Mission 17) segnala come
            // possibile duplicato DI QUESTO, per offrire "Unisci qui"
            // direttamente sulla pagina di modifica — mai un ricalcolo
            // della regola di duplicazione qui.
            'duplicatesOfThisConcept' => $this->duplicatesOf($concept),
        ]);
    }

    public function merge(Concept $concept, Concept $duplicate)
    {
        if ($concept->id === $duplicate->id) {
            return back()->with('error', 'Un concetto non può essere fuso con se stesso.');
        }

        $duplicateName = $duplicate->name;
        $report = $this->conceptMerge->merge($concept, $duplicate);

        return redirect()->route('admin.concepts.edit', $concept)->with('success', sprintf(
            '"%s" è stato fuso in questo concetto: %d alias spostati, %d articoli spostati (%d conflitti risolti), %d domande spostate.',
            $duplicateName,
            $report['aliases_moved'],
            $report['article_links_moved'],
            $report['article_links_conflicts_resolved'],
            $report['questions_moved'],
        ));
    }

    /**
     * @return list<array{id:int,name:string,slug:string,status:string}>
     */
    private function duplicatesOf(Concept $concept): array
    {
        return collect($this->duplicateAudit->audit())
            ->filter(fn (array $group) => collect($group['concepts'])->contains('id', $concept->id))
            ->flatMap(fn (array $group) => $group['concepts'])
            ->filter(fn (array $entry) => $entry['id'] !== $concept->id)
            ->unique('id')
            ->values()
            ->all();
    }

    public function update(Request $request, Concept $concept)
    {
        $data = $this->validatedConcept($request, $concept);
        $concept->update($data);

        $aliases = array_filter((array) $request->input('aliases', []), fn ($alias) => trim((string) $alias) !== '');
        $this->aliasSync->sync($concept, array_values($aliases));

        return redirect()->route('admin.concepts.edit', $concept)->with('success', 'Concetto aggiornato.');
    }

    public function linkArticle(Request $request, Concept $concept, Article $article)
    {
        $validated = $request->validate([
            'relation_type' => ['nullable', Rule::in([ArticleConcept::RELATION_PRIMARY, ArticleConcept::RELATION_SUPPORTING])],
            'weight' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $this->contentGraph->linkArticle(
            $article,
            $concept,
            $validated['relation_type'] ?? ArticleConcept::RELATION_SUPPORTING,
            (int) ($validated['weight'] ?? 50),
        );

        return back()->with('success', 'Articolo collegato al concetto.');
    }

    public function unlinkArticle(Concept $concept, Article $article)
    {
        ArticleConcept::query()
            ->where('concept_id', $concept->id)
            ->where('article_id', $article->id)
            ->delete();

        return back()->with('success', 'Collegamento rimosso.');
    }

    private function validatedConcept(Request $request, ?Concept $concept = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('concepts', 'slug')->ignore($concept?->id)],
            'short_definition' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Concept::STATUS_DRAFT, Concept::STATUS_ACTIVE, Concept::STATUS_INACTIVE])],
        ]);

        return $data;
    }
}
