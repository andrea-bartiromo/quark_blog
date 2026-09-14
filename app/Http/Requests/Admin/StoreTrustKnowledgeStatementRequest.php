<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cantiere 39 (programma "100 cantieri Kairus"): estrae la validazione
 * inline del Cantiere 38 in un FormRequest, la convenzione già stabilita
 * per questo pannello admin (vedi StoreArticleRequest) — TrustKnowledgeStatementController
 * era l'eccezione, non la norma.
 *
 * Due irrigidimenti reali rispetto a prima, entrambi meccanici e
 * verificabili (mai un giudizio sulla qualità del testo — la rubrica
 * umana B-41 di docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md resta
 * deliberatamente non automatizzata, "nessun punteggio opaco"):
 * - last_checked_at non può essere una data futura (stesso principio già
 *   in uso per ArticleSearchProfile::last_editorial_review_at).
 * - concept_id/content_cluster_id devono riferire un Concept/Percorso
 *   attivo, non uno archiviato — stesso pattern già in uso in
 *   StoreArticleRequest per secondary_categories.
 */
class StoreTrustKnowledgeStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'domanda' => ['required', 'string', 'max:300'],
            'consenso' => ['required', 'string', 'max:8000'],
            'incertezza' => ['required', 'string', 'max:8000'],
            'cosa_manca' => ['nullable', 'string', 'max:8000'],
            'last_checked_at' => ['nullable', 'date', 'before_or_equal:today'],
            'last_checked_by' => ['nullable', 'string', 'max:150'],
            'concept_id' => [
                'nullable', 'integer',
                Rule::exists('concepts', 'id')->where(fn ($query) => $query->where('status', 'active')),
            ],
            'content_cluster_id' => [
                'nullable', 'integer',
                Rule::exists('content_clusters', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
