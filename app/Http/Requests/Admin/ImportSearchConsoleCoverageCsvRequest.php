<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportSearchConsoleCoverageCsvRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], 'property' => ['required', 'string', 'max:255'], 'observed_at' => ['required', 'date']];
    }
}
