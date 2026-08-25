<?php

namespace App\Services\SearchConsole;

use App\Models\SearchConsoleQuery;
use Illuminate\Support\Carbon;

/**
 * Missione 34 (secondo batch autonomo KAIRUS, Fase D — Editorial
 * Operations Command Center): "Search Opportunities operational health".
 * Un solo segnale onesto, riusabile ovunque serva: da quanto tempo esiste
 * l'ultimo import CSV di Search Console (colonna imported_at, già scritta
 * da SearchConsoleCsvImporter, mai un nuovo campo o una nuova tabella).
 *
 * Nessuna soglia di staleness, nessuna cronologia import qui: quella
 * valutazione più approfondita è compito dedicato della Fase F (Missione
 * 45 — import freshness) — questo servizio espone solo il dato grezzo,
 * mai anticipata la sua logica di soglia.
 */
class SearchConsoleFreshnessService
{
    /** @return array{available:bool, last_imported_at:?string, days_since_last_import:?int} */
    public function summary(): array
    {
        $lastImportedAt = SearchConsoleQuery::query()->max('imported_at');

        if ($lastImportedAt === null) {
            return [
                'available' => false,
                'last_imported_at' => null,
                'days_since_last_import' => null,
            ];
        }

        $lastImportedAt = Carbon::parse($lastImportedAt);

        return [
            'available' => true,
            'last_imported_at' => $lastImportedAt->toISOString(),
            'days_since_last_import' => (int) $lastImportedAt->diffInDays(now()),
        ];
    }
}
