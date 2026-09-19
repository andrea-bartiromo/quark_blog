<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cantiere 53 (programma "100 cantieri Kairus"). Log append-only degli
 * eventi del benchmark CTR hub categoria (impression della pagina hub +
 * click-through verso un articolo). Nessun identificativo di
 * sessione/visitatore persistito qui — la deduplicazione (vedi
 * CategoryHubCtrBenchmarkService) avviene tramite la sessione Laravel già
 * usata da ArticleViewTrackingService per lo stesso scopo, mai scritta su
 * questa tabella. Stesso pattern di ArticleContinuationEvent (Growth S2):
 * un'unica tabella con `event_type`, non due tabelle separate, così i due
 * lati del funnel restano nello stesso posto e alla stessa granularità.
 */
class CategoryHubEvent extends Model
{
    public const EVENT_IMPRESSION = 'impression';

    public const EVENT_CLICK_THROUGH = 'click_through';

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'category_slug',
    ];
}
