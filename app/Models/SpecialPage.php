<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'description',
        'content',
        'is_active',
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    public static function bySlug(string $slug, array $fallback = []): array
    {
        $page = static::where('slug', $slug)->first();

        if (! $page || ! $page->is_active) {
            return $fallback;
        }

        return array_replace_recursive($fallback, $page->content ?? []);
    }
}
