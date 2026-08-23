<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Content Graph Admin V1 — CRUD di ConceptQuestion. L'admin PUÒ salvare una
 * domanda "approved" senza risposta o senza articolo target: il vincolo di
 * pubblicazione reale (approved + Concept attivo + answer_summary non vuoto
 * + target Article::published()) resta applicato SOLO da
 * ContentGraphService::answerableQuestionsForConcept(), l'unico punto che un
 * futuro consumer pubblico deve interrogare — qui si valida solo la forma
 * dei dati, mai la raggiungibilità pubblica, per non introdurre una seconda
 * versione (probabilmente disallineata) di quella regola.
 */
class ConceptQuestionController extends Controller
{
    public function store(Request $request, Concept $concept)
    {
        $data = $this->validatedQuestion($request);
        $data['concept_id'] = $concept->id;
        ConceptQuestion::create($data);

        return redirect()->route('admin.concepts.edit', $concept)->with('success', 'Domanda aggiunta.');
    }

    public function update(Request $request, Concept $concept, ConceptQuestion $question)
    {
        abort_unless($question->concept_id === $concept->id, 404);

        $question->update($this->validatedQuestion($request, $question));

        return redirect()->route('admin.concepts.edit', $concept)->with('success', 'Domanda aggiornata.');
    }

    private function validatedQuestion(Request $request, ?ConceptQuestion $question = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('question')))]);

        return $request->validate([
            'question' => ['required', 'string', 'max:500'],
            'slug' => ['required', 'string', 'max:180', Rule::unique('concept_questions', 'slug')->ignore($question?->id)],
            'answer_summary' => ['nullable', 'string', 'max:2000'],
            'target_article_id' => ['nullable', 'integer', 'exists:articles,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in([ConceptQuestion::STATUS_DRAFT, ConceptQuestion::STATUS_APPROVED, ConceptQuestion::STATUS_INACTIVE])],
        ]);
    }
}
