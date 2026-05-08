<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function articles()
    {
        return $this->hasMany(Article::class, 'category', 'slug');
    }

    public function publishedArticles()
    {
        return $this->articles()->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('assets/img/categories/' . $this->image) : null;
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

    public static function options(bool $activeOnly = true): array
    {
        try {
            $query = static::query()->ordered();

            if ($activeOnly) {
                $query->active();
            }

            $categories = $query->pluck('name', 'slug')->toArray();

            if ($categories !== []) {
                return $categories;
            }
        } catch (\Throwable $e) {
            // Durante deploy/migrazioni la tabella potrebbe non esistere ancora.
        }

        return config('laboratorio.categories', []);
    }
}
