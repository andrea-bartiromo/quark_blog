<?php

namespace App\Http\Requests\Redazione;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
