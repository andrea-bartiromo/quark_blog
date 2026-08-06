<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectDecisionRequest extends FormRequest
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
            'context' => 'nullable|string',
            'decision' => 'required|string',
            'rationale' => 'nullable|string',
            'status' => ['required', Rule::in(array_keys(ProjectDecision::statusOptions()))],
        ];
    }
}
