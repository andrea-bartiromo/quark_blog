<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cantiere 35 (programma 100-cantieri Kairus). Una riga per dominio/mese
 * (vedi migrazione per il vincolo di unicità e il perché di
 * checked_count/total_count nullable e mai combinati tra domini).
 */
class PublicHealthBaseline extends Model
{
    protected $fillable = [
        'domain',
        'period',
        'finding_count',
        'open_count',
        'dismissed_count',
        'high_open_count',
        'checked_count',
        'total_count',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }
}
