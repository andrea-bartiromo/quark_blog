<?php

namespace App\Services\EditorialOperations;

use App\Models\Article;
use Illuminate\Support\Carbon;

/**
 * Missione 40 (secondo batch autonomo KAIRUS, Fase E — Editorial Quality &
 * Readiness): "publication gaps". Le lacune di sequenza già coperte (Missione
 * 21 — "published_beyond_gap") riguardano un singolo Percorso — un articolo
 * pubblicato oltre un gap nella sua sequenza. Nessun segnale esisteva invece
 * per il ritmo di pubblicazione dell'intero sito: da quanto tempo non esce
 * un nuovo articolo, indipendentemente dai Percorsi.
 *
 * Un solo segnale onesto, riusabile ovunque serva — stesso identico
 * principio già applicato da SearchConsoleFreshnessService (Missione 34):
 * nessuna soglia di "quanto è troppo" inventata qui (non definita nel
 * repository), solo il dato grezzo.
 */
class PublicationCadenceService
{
    /** @return array{available:bool, last_published_at:?string, days_since_last_publication:?int} */
    public function summary(): array
    {
        $lastPublishedAt = Article::query()->published()->max('published_at');

        if ($lastPublishedAt === null) {
            return [
                'available' => false,
                'last_published_at' => null,
                'days_since_last_publication' => null,
            ];
        }

        $lastPublishedAt = Carbon::parse($lastPublishedAt);

        return [
            'available' => true,
            'last_published_at' => $lastPublishedAt->toISOString(),
            'days_since_last_publication' => (int) $lastPublishedAt->diffInDays(now()),
        ];
    }
}
