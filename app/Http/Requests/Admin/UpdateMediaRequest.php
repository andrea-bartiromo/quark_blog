<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isEditor() === true;
    }

    public function rules(): array
    {
        return [
            'alt_text' => ['nullable', 'string', 'max:200'],
            'caption' => ['nullable', 'string', 'max:1000'],
            'credit' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'license' => ['nullable', 'string', 'max:255'],
        ];
    }
}
