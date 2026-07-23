<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MoveMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isEditor() === true;
    }

    public function rules(): array
    {
        return [
            'media_folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }
}
