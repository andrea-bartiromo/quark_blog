<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
            'description' => 'nullable|string',
            'objective' => 'nullable|string',
            'type' => ['required', Rule::in(array_keys(Project::typeOptions()))],
            'operational_status' => ['required', Rule::in(array_keys(Project::statusOptions()))],
            'priority' => ['required', Rule::in(array_keys(Project::priorityOptions()))],
            'responsible_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'next_action' => 'nullable|string|max:255',
            'progress' => 'nullable|integer|min:0|max:100',
            'internal_notes' => 'nullable|string',
        ];
    }
}
