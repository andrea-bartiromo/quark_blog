<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        'views' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * 'date' non usa il cast generico 'date'/'datetime' del framework: il
     * formato effettivamente persistito da quei cast non è garantito
     * indipendente dal driver (verificato corretto su SQLite in questo
     * progetto, ma non testabile qui su MySQL/PostgreSQL). Un accessor/
     * mutator esplicito elimina ogni dipendenza da quel comportamento:
     * scrive sempre 'Y-m-d' puro, sullo stesso formato dell'upsert SQL
     * grezzo in ArticleViewTrackingService — senza questa garanzia le
     * query per intervallo di ArticleAnalyticsService (confronto stringa
     * 'Y-m-d') potrebbero mancare silenziosamente le righe scritte tramite
     * il model Eloquent invece che tramite il service.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : Carbon::parse($value)->format('Y-m-d'),
        );
    }
}
