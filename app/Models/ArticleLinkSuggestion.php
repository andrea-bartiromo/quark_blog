<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un suggerimento di collegamento interno — SEMPRE una proposta da
 * rivedere, mai un'azione già applicata. Il body dell'articolo sorgente
 * viene modificato solo quando la redazione preme esplicitamente
 * "Inserisci" (App\Services\ArticleLinkInsertionService), e comunque mai
 * salvato automaticamente.
 */
class ArticleLinkSuggestion extends Model
{
    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_SUPERSEDED = 'superseded';

    /** Meno suggerimenti, più pertinenti: limite alla lista mostrata in redazione (Audit qualità suggerimenti, Ago 2026). */
    public const MAX_PROPOSED_RESULTS = 5;

    protected $fillable = [
        'source_article_id',
        'target_article_id',
        'anchor_text',
        'context_excerpt',
        'reason',
        'confidence_score',
        'status',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'confidence_score' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function sourceArticle()
    {
        return $this->belongsTo(Article::class, 'source_article_id');
    }

    public function targetArticle()
    {
        return $this->belongsTo(Article::class, 'target_article_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeProposed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PROPOSED);
    }

    public function scopeForSource(Builder $q, int $articleId): Builder
    {
        return $q->where('source_article_id', $articleId);
    }

    public function isActionable(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }
}
