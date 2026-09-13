<?php

namespace App\Services\SearchConsole;

use App\Models\SearchConsoleImportCoverage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cantiere 1 (programma "Kairus Organic Discovery"). Registra la copertura
 * *effettiva* dei dati Search Console importati — property, periodo, tipo
 * di report, righe importate, query assegnate/non assegnate a un
 * articolo, pagine osservate, origine — cosi' la redazione vede subito
 * cosa e' stato importato senza dover leggere search_console_queries riga
 * per riga.
 *
 * Una sola riga per property/periodo/tipo di report (upsert, stesso
 * pattern atomico gia' stabilito da PublicHealthBaselineService): un
 * secondo import dello stesso periodo aggiorna la copertura esistente
 * invece di accumulare uno storico di ogni singolo import. La cronologia
 * dei singoli import (per batch) resta compito di
 * SearchConsoleFreshnessService::importHistory(), qui non duplicata.
 */
class SearchConsoleImportCoverageService
{
    public function resolveProperty(?string $property): string
    {
        $property = $property !== null ? trim($property) : '';

        if ($property !== '') {
            return $property;
        }

        $default = config('search-console.default_property');

        if (is_string($default) && trim($default) !== '') {
            return trim($default);
        }

        return (string) config('app.url');
    }

    /**
     * @param  array{
     *   property: string,
     *   period_start: CarbonInterface,
     *   period_end: CarbonInterface,
     *   report_type: string,
     *   row_count: int,
     *   matched_count: int,
     *   unmatched_count: int,
     *   pages_observed_count: int,
     *   import_batch: string,
     *   origin?: string,
     * }  $data
     */
    public function record(array $data): SearchConsoleImportCoverage
    {
        $importedAt = Carbon::now();

        /*
         * SearchConsoleCsvImporter::import() sostituisce le righe di
         * search_console_queries per l'intero period_start/period_end,
         * indipendentemente da property o tipo di report (nessun filtro
         * su queste colonne nella sua query di cancellazione). Una riga
         * di copertura già registrata per questo stesso periodo ma con
         * una property o un tipo di report diversi descrive quindi dati
         * che non esistono più: va rimossa, altrimenti l'admin la
         * mostrerebbe ancora come "corrente" insieme alla nuova.
         */
        SearchConsoleImportCoverage::query()
            ->whereDate('period_start', $data['period_start']->toDateString())
            ->whereDate('period_end', $data['period_end']->toDateString())
            ->where(function ($query) use ($data) {
                $query->where('property', '!=', $data['property'])
                    ->orWhere('report_type', '!=', $data['report_type']);
            })
            ->delete();

        SearchConsoleImportCoverage::query()->upsert([
            [
                'property' => $data['property'],
                'period_start' => $data['period_start']->toDateString(),
                'period_end' => $data['period_end']->toDateString(),
                'report_type' => $data['report_type'],
                'row_count' => $data['row_count'],
                'matched_count' => $data['matched_count'],
                'unmatched_count' => $data['unmatched_count'],
                'pages_observed_count' => $data['pages_observed_count'],
                'origin' => $data['origin'] ?? SearchConsoleImportCoverage::ORIGIN_MANUAL_CSV,
                'import_batch' => $data['import_batch'],
                'imported_at' => $importedAt,
                'created_at' => $importedAt,
                'updated_at' => $importedAt,
            ],
        ], ['property', 'period_start', 'period_end', 'report_type'], [
            'row_count', 'matched_count', 'unmatched_count', 'pages_observed_count',
            'origin', 'import_batch', 'imported_at', 'updated_at',
        ]);

        return SearchConsoleImportCoverage::query()
            ->where('property', $data['property'])
            ->whereDate('period_start', $data['period_start']->toDateString())
            ->whereDate('period_end', $data['period_end']->toDateString())
            ->where('report_type', $data['report_type'])
            ->firstOrFail();
    }

    /** @return Collection<int, SearchConsoleImportCoverage> */
    public function all(): Collection
    {
        return SearchConsoleImportCoverage::query()
            ->orderByDesc('period_start')
            ->orderBy('property')
            ->get();
    }
}
