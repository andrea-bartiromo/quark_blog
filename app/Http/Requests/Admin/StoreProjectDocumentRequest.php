<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectDocumentRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'media_id' => 'nullable|exists:media,id',
            'type' => ['required', Rule::in(array_keys(ProjectDocument::typeOptions()))],
            'status' => ['required', Rule::in(array_keys(ProjectDocument::statusOptions()))],
        ];
    }
}
