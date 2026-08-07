<?php

namespace App\Http\Requests\Admin;

use App\Models\ProjectTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectTaskRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_keys(ProjectTask::typeOptions()))],
            'manual_status' => ['required', Rule::in(array_keys(ProjectTask::statusOptions()))],
            'priority' => ['required', Rule::in(array_keys(ProjectTask::priorityOptions()))],
            'responsible_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'article_id' => 'nullable|exists:articles,id',
            'github_branch' => 'nullable|string|max:255',
            'manual_override' => 'boolean',
        ];
    }
}
