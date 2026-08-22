<?php

namespace App\Http\Requests\Redazione;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|max:200',
            'excerpt' => 'nullable|max:300',
            'body' => 'required',
            // Validato contro le categorie DB realmente attive (stessa fonte
            // di Admin\StoreArticleRequest via Category::options()), non più
            // contro lo snapshot statico di config('laboratorio.categories'):
            // quest'ultimo era rimasto disallineato dalla Libreria categorie
            // reale (es. non conteneva "fisica" nonostante fosse già una
            // categoria editoriale primaria), bloccando in modo permanente
            // qualunque autore Redazione dal pubblicare in quella categoria.
            'category' => ['required', Rule::in(array_keys(Category::options()))],
            'cover_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'cover_image' => 'nullable|max:255',
            'cover_alt' => 'nullable|string|max:255',
            'cover_caption' => 'nullable|string|max:1000',
            'cover_credit' => 'nullable|string|max:255',
            'cover_source' => 'nullable|string|max:255',
            'cover_source_url' => 'nullable|url|max:2048',
            'cover_license' => 'nullable|string|max:255',
            'read_minutes' => 'nullable|integer|min:1|max:60',
            'seo_title' => 'nullable|string|max:70',
            'seo_description' => 'nullable|string|max:200',
            'canonical_url' => 'nullable|url|max:2048',
            'robots' => ['nullable', Rule::in(Article::robotsOptions())],
            'og_title' => 'nullable|string|max:70',
            'og_description' => 'nullable|string|max:200',
            'og_image' => 'nullable|max:255',
            'twitter_title' => 'nullable|string|max:70',
            'twitter_description' => 'nullable|string|max:200',
            'twitter_image' => 'nullable|max:255',
        ];
    }
}
