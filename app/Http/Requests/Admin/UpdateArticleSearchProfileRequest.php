<?php

namespace App\Http\Requests\Admin;

use App\Models\ArticleSearchProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cantiere 2 (programma "Kairus Organic Discovery"). Query
 * secondarie/domande dei lettori arrivano dal form come una per riga
 * (textarea, nessun JavaScript richiesto): prepareForValidation() le
 * converte in un array prima delle regole, cosi' "max:10" e "distinct"
 * si applicano alle singole voci, non al blob di testo.
 */
class UpdateArticleSearchProfileRequest extends FormRequest
{
    private const MAX_LIST_ITEMS = 10;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'secondary_queries' => $this->linesToArray($this->input('secondary_queries')),
            'reader_questions' => $this->linesToArray($this->input('reader_questions')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'primary_intent' => ['nullable', 'string', 'max:500'],
            'primary_query' => ['nullable', 'string', 'max:255'],
            'secondary_queries' => ['nullable', 'array', 'max:'.self::MAX_LIST_ITEMS],
            'secondary_queries.*' => ['string', 'max:255', 'distinct:strict'],
            'reader_questions' => ['nullable', 'array', 'max:'.self::MAX_LIST_ITEMS],
            'reader_questions.*' => ['string', 'max:500', 'distinct:strict'],
            'content_type' => ['nullable', Rule::in(array_keys(ArticleSearchProfile::contentTypeOptions()))],
            'reader_level' => ['nullable', Rule::in(array_keys(ArticleSearchProfile::readerLevelOptions()))],
            'last_editorial_review_at' => ['nullable', 'date', 'before_or_equal:today'],
            'freshness_note' => ['nullable', 'string', 'max:1000'],
            'evidence_scope' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return list<string> */
    private function linesToArray(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $raw))
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }
}
