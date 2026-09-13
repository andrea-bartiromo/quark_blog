<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportSearchConsoleCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            // Cantiere 1 (programma "Kairus Organic Discovery"): opzionale,
            // usata per distinguere più property Search Console in
            // futuro. Se assente, l'importer risolve un default (vedi
            // SearchConsoleImportCoverageService::resolveProperty()).
            'property' => ['nullable', 'string', 'max:255'],
        ];
    }
}
