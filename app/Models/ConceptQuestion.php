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

    /**
     * Canonical query fragment for the public-answerable question gate.
     *
     * Concept activity remains a property of the parent Concept and is checked
     * by ContentGraphService. Keeping the question-side predicates here lets
     * aggregate diagnostics reuse the exact same rule without per-Concept
     * queries or a second implementation.
     */
    public function scopePubliclyAnswerable(Builder $query): Builder
    {
        return $query
            ->approved()
            ->whereNotNull('target_article_id')
            ->whereNotNull('answer_summary')
            ->whereRaw("TRIM(answer_summary) <> ''")
            ->whereHas('targetArticle', fn (Builder $query) => $query->published());
    }

    public function setQuestionAttribute(string $value): void
    {
        $this->attributes['question'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }
}
