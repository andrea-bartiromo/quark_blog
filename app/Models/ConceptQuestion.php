<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConceptQuestion extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'concept_id',
        'question',
        'slug',
        'answer_summary',
        'target_article_id',
        'sort_order',
        'status',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $casts = [
        'target_article_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function concept()
    {
        return $this->belongsTo(Concept::class);
    }

    public function targetArticle()
    {
        return $this->belongsTo(Article::class, 'target_article_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function setQuestionAttribute(string $value): void
    {
        $this->attributes['question'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }
}
