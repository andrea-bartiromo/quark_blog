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
 * Missione 45 (Fase F — Search Intelligence): "import freshness".
 * Nessuna soglia numerica di staleness è stata aggiunta qui — non è
 * definita nel repository né nell'API di Search Console (nessun
 * riferimento al suo delay di pubblicazione dati è mai stato documentato
 * nel codebase), e questo batch non ne inventa una arbitraria (stesso
 * principio già applicato da ArticleContentHealthService::freshness() e
 * PublicationCadenceService). Il gap reale era diverso: ogni import CSV è
 * già idempotente-per-periodo (SearchConsoleCsvImporter sostituisce solo
 * le righe dello stesso period_start/period_end, mai le altre — vedi la
 * sua docblock), quindi importi di periodi diversi si accumulano davvero
 * nel tempo, ma nessuna vista ne mostrava mai la cronologia — solo
 * l'ultimo import. importHistory() la espone.
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

    /**
     * Un import per riga (import_batch + periodo), più recente per primo —
     * stessa idempotenza-per-periodo già garantita da
     * SearchConsoleCsvImporter, mai una nuova regola di raggruppamento.
     *
     * @return array<int, array{import_batch:string, period_start:string, period_end:string, imported_at:string, row_count:int}>
     */
    public function importHistory(): array
    {
        return SearchConsoleQuery::query()
            ->selectRaw('import_batch, period_start, period_end, max(imported_at) as imported_at, count(*) as row_count')
            ->groupBy('import_batch', 'period_start', 'period_end')
            ->orderByDesc('imported_at')
            ->get()
            ->map(fn ($row) => [
                'import_batch' => $row->import_batch,
                'period_start' => (string) $row->period_start,
                'period_end' => (string) $row->period_end,
                'imported_at' => Carbon::parse($row->imported_at)->toISOString(),
                'row_count' => (int) $row->row_count,
            ])
            ->all();
    }
}
