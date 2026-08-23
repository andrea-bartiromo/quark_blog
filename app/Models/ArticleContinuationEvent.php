<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log append-only degli eventi del funnel "Continua da qui" (Growth S2).
 * Nessun identificativo di sessione/visitatore persistito qui — la
 * deduplicazione (vedi ContinuationAnalyticsService) avviene tramite la
 * sessione Laravel già usata da ArticleViewTrackingService per lo stesso
 * scopo, mai scritta su questa tabella.
 */
class ArticleContinuationEvent extends Model
{
    public const EVENT_IMPRESSION = 'impression';

    public const EVENT_SECOND_READ_START = 'second_read_start';

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'source_article_id',
        'target_article_id',
    ];

    public function sourceArticle()
    {
        return $this->belongsTo(Article::class, 'source_article_id');
    }

    public function targetArticle()
    {
        return $this->belongsTo(Article::class, 'target_article_id');
    }
}
