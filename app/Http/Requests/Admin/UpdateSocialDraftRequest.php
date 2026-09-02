<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'copy' => ['nullable', 'string', 'max:10000'],
            'destination_url' => ['nullable', 'string', 'max:2048'],
            'use_utm' => ['nullable', 'boolean'],
            'utm_campaign' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'scheduled_date' => ['nullable', 'date_format:Y-m-d'],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'utm_campaign.regex' => 'Nome campagna non valido: solo lettere minuscole, cifre e trattini singoli (es. "lancio-fisica-2026").',
            'scheduled_date.date_format' => 'Data non valida.',
            'scheduled_time.date_format' => 'Ora non valida (formato HH:MM).',
        ];
    }
}
