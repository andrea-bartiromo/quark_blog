<?php

namespace App\Http\Requests\Redazione;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;

class UpdateArticleRequest extends FormRequest
{
    /**
     * Replica esattamente i due controlli che il controller eseguiva prima
     * di validare (solo l'autore proprietario può modificare, e non se
     * l'articolo è già pubblicato), cosi che l'esito e l'ordine restino
     * identici a quelli precedenti: l'autorizzazione viene verificata prima
     * della validazione, esattamente come i due `abort(403)` originali.
     */
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof Article
            && $article->user_id === auth()->id()
            && $article->status !== 'published';
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'title'               => 'required|max:200',
            'excerpt'             => 'nullable|max:300',
            'body'                => 'required',
            'category'            => 'required|in:' . implode(',', array_keys(config('laboratorio.categories'))),
            'cover_image_upload'  => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'cover_image'         => 'nullable|max:255',
            'cover_alt'           => 'nullable|string|max:255',
            'cover_caption'       => 'nullable|string|max:1000',
            'cover_credit'        => 'nullable|string|max:255',
            'cover_source'        => 'nullable|string|max:255',
            'cover_source_url'    => 'nullable|url|max:2048',
            'cover_license'       => 'nullable|string|max:255',
            'read_minutes'        => 'nullable|integer|min:1|max:60',
        ];
    }
}
