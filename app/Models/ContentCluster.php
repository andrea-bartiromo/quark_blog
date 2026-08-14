<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentCluster extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'cover_image',
        'seo_title',
        'seo_description',
        'pillar_article_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'pillar_article_id' => 'integer',
    ];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_content_cluster')
            ->withPivot(['position', 'is_primary'])
            ->withTimestamps()
            ->orderByPivot('position')
            ->orderBy('articles.title');
    }

    public function pillarArticle()
    {
        return $this->belongsTo(Article::class, 'pillar_article_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }
}
