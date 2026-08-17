<?php

namespace App\Http\Requests\Admin;

use App\Models\CommunicationSenderProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommunicationSenderProfileRequest extends FormRequest
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
            // Niente \r/\n: from_name diventa il display-name dell'header
            // From di un'email reale quando un provider verrà collegato —
            // stesso vettore di CRLF header injection già bloccato su
            // subject/preheader della campagna.
            'from_name' => ['required', 'string', 'max:255', 'regex:/^[^\r\n]*$/'],
            'from_email' => 'required|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'provider' => ['required', Rule::in(array_keys(CommunicationSenderProfile::providerOptions()))],
            'status' => ['required', Rule::in(array_keys(CommunicationSenderProfile::statusOptions()))],
            'is_default' => 'boolean',
        ];
    }
}
