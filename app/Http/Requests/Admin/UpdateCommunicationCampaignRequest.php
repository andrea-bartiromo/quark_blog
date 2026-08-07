<?php

namespace App\Http\Requests\Admin;

use App\Models\CommunicationCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunicationCampaignRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_keys(CommunicationCampaign::typeOptions()))],
            'project_id' => 'nullable|exists:projects,id',
            'template_id' => 'nullable|exists:comm_templates,id',
            'template_version_id' => 'nullable|exists:comm_template_versions,id',
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'subject' => 'required|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'body' => 'nullable|string',
        ];
    }
}
