<?php

namespace App\Http\Requests\Admin;

use App\Models\CommunicationCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationCampaignRequest extends FormRequest
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
            'sender_profile_id' => 'nullable|exists:comm_sender_profiles,id',
            'template_id' => 'nullable|exists:comm_templates,id',
            'template_version_id' => [
                'nullable',
                Rule::exists('comm_template_versions', 'id')->where(
                    fn ($query) => $query->where('template_id', $this->input('template_id'))
                ),
            ],
            'description' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            // Niente \r/\n: questi campi diventeranno header email veri
            // (Subject) quando un provider reale verrà collegato — un
            // carattere di ritorno a capo qui è un vettore classico di
            // header injection (CRLF), rifiutato già alla validazione
            // invece di essere solo ripulito più a valle nel rendering.
            'subject' => ['required', 'string', 'max:255', 'regex:/^[^\r\n]*$/'],
            'preheader' => ['nullable', 'string', 'max:255', 'regex:/^[^\r\n]*$/'],
            'body' => 'nullable|string',
        ];
    }
}
