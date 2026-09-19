<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cantiere 53 (programma "100 cantieri Kairus"). Log append-only delle
 * impression di una pagina hub categoria (ArticleController::category()).
 * Nessun identificativo di sessione/visitatore persistito qui — la
 * deduplicazione (vedi CategoryHubCtrBenchmarkService) avviene tramite la
 * sessione Laravel già usata da ArticleViewTrackingService per lo stesso
 * scopo, mai scritta su questa tabella. Stesso pattern di
 * ArticleContinuationEvent (Growth S2).
 */
class CategoryHubImpression extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'category_slug',
    ];
}
