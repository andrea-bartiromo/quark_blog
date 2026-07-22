<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isEditor() === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $name = (string) $this->input('name');

                if (str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\') || in_array(trim($name), ['.', '..'], true)) {
                    $validator->errors()->add('name', 'Il nome non può contenere slash, backslash, null byte o segmenti relativi.');
                }
            },
        ];
    }
}
