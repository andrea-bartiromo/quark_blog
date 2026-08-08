<?php

namespace App\Http\Requests\Admin;

use App\Models\CommunicationSenderProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunicationSenderProfileRequest extends FormRequest
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
            'from_name' => 'required|string|max:255',
            'from_email' => 'required|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'provider' => ['required', Rule::in(array_keys(CommunicationSenderProfile::providerOptions()))],
            'is_default' => 'boolean',
        ];
    }
}
