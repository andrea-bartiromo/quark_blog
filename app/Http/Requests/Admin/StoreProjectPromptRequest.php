<?php

namespace App\Http\Requests\Admin;

use App\Models\Project;
use App\Models\ProjectPrompt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectPromptRequest extends FormRequest
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
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'title' => 'required|string|max:255',
            'agent' => 'nullable|string|max:255',
            'content' => 'required|string',
            'status' => ['required', Rule::in(array_keys(ProjectPrompt::statusOptions()))],
            'outcome' => 'nullable|string',
            'article_id' => 'nullable|exists:articles,id',
            'task_id' => [
                'nullable',
                Rule::exists('project_tasks', 'id')->where(
                    fn ($query) => $query->where('project_id', $project->id)
                ),
            ],
        ];
    }
}
