<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardDataExportRequest extends FormRequest
{
    /** @var list<string> */
    public const SECTIONS = ['dashboard-summary', 'content-health', 'second-read', 'newsletter-summary'];

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'timezone' => ['required', Rule::in([config('dashboard_data_export.timezone')])],
            'format' => ['required', Rule::in(['zip', 'csv', 'json'])],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'distinct', Rule::in(self::SECTIONS)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $from = CarbonImmutable::parse($this->string('from')->toString(), config('dashboard_data_export.timezone'))->startOfDay();
            $to = CarbonImmutable::parse($this->string('to')->toString(), config('dashboard_data_export.timezone'))->endOfDay();

            if ($from->diffInDays($to) > config('dashboard_data_export.max_range_days')) {
                $validator->errors()->add('to', 'L’intervallo supera il limite massimo consentito.');
            }

            if ($this->string('format')->toString() === 'csv' && count($this->input('sections', [])) !== 1) {
                $validator->errors()->add('sections', 'Il formato CSV richiede una sola sezione.');
            }
        });
    }
}
