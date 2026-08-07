<?php

namespace App\Http\Requests\Admin;

use App\Models\CommunicationCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationTemplateRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['nullable', Rule::in(array_keys(CommunicationCampaign::typeOptions()))],
            'subject' => 'required|string|max:255',
            'preheader' => 'nullable|string|max:255',
            'body' => 'nullable|string',
        ];
    }
}
