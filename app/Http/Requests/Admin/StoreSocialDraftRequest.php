<?php

namespace App\Http\Requests\Admin;

use App\Models\SocialDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Autorizzazione già garantita dal middleware ['auth','editor'] sull'intero
 * gruppo di rotte admin (stesso pattern del resto del progetto, che non usa
 * Policy dedicate) — authorize() qui non deve fare da secondo cancello.
 */
class StoreSocialDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'article_id' => ['required', 'integer', 'exists:articles,id'],
            'channel' => ['required', 'string', Rule::in(array_keys(SocialDraft::CHANNELS))],
            'copy' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'article_id.required' => 'Seleziona un articolo.',
            'article_id.exists' => 'Articolo non trovato.',
            'channel.required' => 'Seleziona un canale.',
            'channel.in' => 'Canale non supportato: solo Facebook o LinkedIn.',
        ];
    }
}
