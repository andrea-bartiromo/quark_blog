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

/**
 * Un path pubblico che ha risposto 404 almeno una volta a traffico reale
 * (mai a un audit interno — vedi App\Services\PublicPages\NotFoundHitTracker),
 * aggregato per path: un solo record per path, con un contatore di
 * occorrenze invece di un log per-hit, cosi' un editore vede subito quali
 * link rotti valgono la pena di un redirect senza dover contare righe.
 */
class NotFoundHit extends Model
{
    protected $fillable = [
        'path_hash', 'path', 'hits', 'last_referer', 'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
