<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
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
 * - last_checked_at non può essere una data futura, confrontata nel
 *   fuso editoriale (Article::EDITORIAL_TIMEZONE, Europe/Rome) e non nel
 *   fuso applicativo (UTC) — altrimenti nella prima ora/due dopo
 *   mezzanotte a Roma un editor che inserisce la data odierna locale se
 *   la vede rifiutata come "futura" rispetto a un "oggi" ancora fermo al
 *   giorno prima in UTC (Codex, PR #596).
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
            'last_checked_at' => ['nullable', 'date', 'before_or_equal:'.now(Article::EDITORIAL_TIMEZONE)->toDateString()],
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
