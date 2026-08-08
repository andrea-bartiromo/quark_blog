<?php

/**
 * Kairus — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://kairus.it
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vecchio slug di un articolo che ha cambiato URL: usato da
 * ArticleController::show() per reindirizzare permanentemente (301) chi
 * arriva su un link ormai superato — es. da un motore di ricerca non
 * ancora ricrawlato, o da un link esterno — invece di restituire 404.
 * Popolato automaticamente da Article::booted() a ogni cambio di slug.
 */
class ArticleSlugRedirect extends Model
{
    protected $fillable = [
        'old_slug', 'article_id',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
