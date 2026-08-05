<?php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreArticleRequest extends FormRequest
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
            'title' => 'required|max:255',
            'excerpt' => 'nullable|max:300',
            'body' => 'required',
            'category' => 'required',
            'cover_image' => 'nullable|max:255',
            'cover_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'cover_alt' => 'nullable|string|max:255',
            'cover_caption' => 'nullable|string|max:1000',
            'cover_credit' => 'nullable|string|max:255',
            'cover_source' => 'nullable|string|max:255',
            'cover_source_url' => 'nullable|url|max:2048',
            'cover_license' => 'nullable|string|max:255',
            'status' => ['required', Rule::in([
                Article::STATUS_DRAFT,
                Article::STATUS_REVIEW,
                Article::STATUS_SCHEDULED,
                Article::STATUS_PUBLISHED,
            ])],
            'published_date' => 'required_if:status,'.Article::STATUS_SCHEDULED.'|nullable|date_format:Y-m-d',
            'published_time' => 'required_if:status,'.Article::STATUS_SCHEDULED.'|nullable|date_format:H:i',
            'read_minutes' => 'integer|min:1|max:60',
            'featured' => 'boolean',
            'seo_title' => 'nullable|string|max:70',
            'seo_description' => 'nullable|string|max:200',
            'canonical_url' => 'nullable|url|max:2048',
            'robots' => ['nullable', Rule::in(Article::robotsOptions())],
            'og_title' => 'nullable|string|max:70',
            'og_description' => 'nullable|string|max:200',
            'og_image' => 'nullable|max:255',
            'twitter_title' => 'nullable|string|max:70',
            'twitter_description' => 'nullable|string|max:200',
            'twitter_image' => 'nullable|max:255',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('status') !== Article::STATUS_SCHEDULED) {
                return;
            }

            $date = $this->input('published_date');
            $time = $this->input('published_time');

            if (! $date || ! $time || $validator->errors()->hasAny(['published_date', 'published_time'])) {
                return;
            }

            try {
                $scheduledAt = Article::scheduledAtFromEditorialInput($date, $time);
            } catch (\Throwable) {
                $validator->errors()->add('published_date', 'Data o ora non valide.');

                return;
            }

            if ($scheduledAt->isPast()) {
                $validator->errors()->add('published_date', 'La data e l\'ora di pubblicazione programmata devono essere nel futuro.');
            }
        });
    }
}
