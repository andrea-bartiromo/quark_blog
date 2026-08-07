<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregato giornaliero delle views pubbliche di un articolo — una riga per
 * (article_id, date), mai una riga per singola pageview. Scritta solo da
 * App\Services\ArticleViewTrackingService, letta da
 * App\Services\ArticleAnalyticsService.
 */
class ArticleDailyView extends Model
{
    protected $fillable = [
        'article_id',
        'date',
        'views',
    ];

    protected $casts = [
        // Formato esplicito: senza 'Y-m-d' il cast 'date' generico persiste
        // un timestamp completo ('2026-08-19 00:00:00'), disallineato dalle
        // righe scritte con SQL grezzo (upsert atomico in
        // ArticleViewTrackingService, che scrive 'Y-m-d' puro) — le query
        // per intervallo di ArticleAnalyticsService confrontano stringhe
        // 'Y-m-d' e mancherebbero le righe nell'altro formato.
        'date' => 'date:Y-m-d',
        'views' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
