<?php

namespace App\Http\Requests\Admin;

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
            'title'              => 'required|max:255',
            'excerpt'            => 'nullable|max:300',
            'body'               => 'required',
            'category'           => 'required',
            'cover_image'        => 'nullable|max:255',
            'cover_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'cover_alt'          => 'nullable|string|max:255',
            'cover_caption'      => 'nullable|string|max:1000',
            'cover_credit'       => 'nullable|string|max:255',
            'cover_source'       => 'nullable|string|max:255',
            'cover_source_url'   => 'nullable|url|max:2048',
            'cover_license'      => 'nullable|string|max:255',
            'status'             => 'required|in:draft,published,review',
            'read_minutes'       => 'integer|min:1|max:60',
            'featured'           => 'boolean',
        ];
    }
}
