<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una riga (query + pagina, aggregata su un periodo) importata da un
 * export CSV di Google Search Console. Vedi
 * App\Services\SearchConsole\SearchConsoleCsvImporter per l'unico punto di
 * scrittura e docs/SEARCH_OPPORTUNITIES.md per il formato CSV atteso.
 */
class SearchConsoleQuery extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'query',
        'page_url',
        'article_id',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'period_start',
        'period_end',
        'import_batch',
        'imported_at',
    ];

    protected $casts = [
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
        'period_start' => 'date',
        'period_end' => 'date',
        'imported_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
