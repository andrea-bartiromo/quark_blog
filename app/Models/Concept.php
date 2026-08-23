<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Concept extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'slug',
        'short_definition',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    public function aliases()
    {
        return $this->hasMany(ConceptAlias::class);
    }

    public function articleLinks()
    {
        return $this->hasMany(ArticleConcept::class);
    }

    public function questions()
    {
        return $this->hasMany(ConceptQuestion::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }
}
