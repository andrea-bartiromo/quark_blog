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
        'date' => 'date',
        'views' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
