<?php

namespace App\Services\SearchConsole;

use App\Models\SearchConsoleQuery;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa un export CSV di Search Console (dimensioni query + pagina) in
 * search_console_queries. Formato atteso e come ottenerlo: vedi
 * docs/SEARCH_OPPORTUNITIES.md. Nessuna chiamata di rete: legge solo il
 * file locale già caricato dal controller admin.
 *
 * Idempotente per periodo: un secondo import con lo stesso
 * (period_start, period_end) sostituisce le righe di quel periodo invece
 * di sommarsi — un redattore che ri-esporta o corregge un file può
 * ripetere l'import senza doppio conteggio.
 */
class SearchConsoleCsvImporter
{
    private const REQUIRED_COLUMNS = ['query', 'page', 'clicks', 'impressions', 'ctr', 'position'];

    private const MAX_ROWS = 50000;

    public function __construct(
        private readonly SearchConsoleQueryArticleMatcher $matcher
    ) {}

    public function import(string $filePath, CarbonInterface $periodStart, CarbonInterface $periodEnd): SearchConsoleImportResult
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return new SearchConsoleImportResult(0, 0, 0, ['Impossibile aprire il file.'], '');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                return new SearchConsoleImportResult(0, 0, 0, ['File vuoto.'], '');
            }

            $columnIndex = $this->resolveColumnIndex($header);

            if ($columnIndex === null) {
                return new SearchConsoleImportResult(0, 0, 0, [
                    'Intestazioni mancanti. Colonne richieste: '.implode(', ', self::REQUIRED_COLUMNS).'.',
                ], '');
            }

            $importBatch = (string) Str::uuid();
            $rows = [];
            $errors = [];
            $matched = 0;
            $lineNumber = 1;

            while (($record = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if (count($rows) >= self::MAX_ROWS) {
                    $errors[] = "Riga {$lineNumber}: limite di ".self::MAX_ROWS.' righe per import raggiunto, resto del file ignorato.';

                    break;
                }

                $parsed = $this->parseRow($record, $columnIndex, $lineNumber);

                if ($parsed === null) {
                    $errors[] = "Riga {$lineNumber}: valori non validi, riga scartata.";

                    continue;
                }

                $article = $this->matcher->match($parsed['page_url']);

                if ($article) {
                    $matched++;
                }

                $rows[] = [
                    ...$parsed,
                    'article_id' => $article?->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'import_batch' => $importBatch,
                    'imported_at' => now(),
                ];
            }

            if (empty($rows)) {
                return new SearchConsoleImportResult(0, 0, 0, [...$errors, 'Nessuna riga valida trovata.'], '');
            }

            DB::transaction(function () use ($rows, $periodStart, $periodEnd) {
                // Sostituisce, non somma: stesso periodo re-importato non
                // deve mai raddoppiare impression/click esistenti.
                SearchConsoleQuery::query()
                    ->whereDate('period_start', $periodStart->toDateString())
                    ->whereDate('period_end', $periodEnd->toDateString())
                    ->delete();

                foreach (array_chunk($rows, 500) as $chunk) {
                    SearchConsoleQuery::query()->insert($chunk);
                }
            });

            return new SearchConsoleImportResult(
                imported: count($rows),
                matchedToArticle: $matched,
                unmatched: count($rows) - $matched,
                errors: $errors,
                importBatch: $importBatch,
            );
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string,int>|null
     */
    private function resolveColumnIndex(array $header): ?array
    {
        $normalized = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $index = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            $position = array_search($column, $normalized, true);

            if ($position === false) {
                return null;
            }

            $index[$column] = $position;
        }

        return $index;
    }

    /**
     * @return array{query:string,page_url:string,clicks:int,impressions:int,ctr:float,position:float}|null
     */
    private function parseRow(array $record, array $columnIndex, int $lineNumber): ?array
    {
        $query = trim((string) ($record[$columnIndex['query']] ?? ''));
        $pageUrl = trim((string) ($record[$columnIndex['page']] ?? ''));

        if ($query === '' || $pageUrl === '') {
            return null;
        }

        $clicks = filter_var($record[$columnIndex['clicks']] ?? null, FILTER_VALIDATE_INT);
        $impressions = filter_var($record[$columnIndex['impressions']] ?? null, FILTER_VALIDATE_INT);
        $ctr = $this->parseCtr($record[$columnIndex['ctr']] ?? null);
        $position = filter_var($record[$columnIndex['position']] ?? null, FILTER_VALIDATE_FLOAT);

        if ($clicks === false || $impressions === false || $ctr === null || $position === false) {
            return null;
        }

        if ($clicks < 0 || $impressions < 0 || $ctr < 0 || $position < 0) {
            return null;
        }

        return [
            'query' => mb_substr($query, 0, 255),
            'page_url' => mb_substr($pageUrl, 0, 500),
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $ctr,
            'position' => $position,
        ];
    }

    /**
     * Search Console esporta il CTR come percentuale testuale (es. "4.52%")
     * ma accettiamo anche un decimale grezzo 0-1 per import non originati
     * dall'export standard (es. rigenerati a mano).
     */
    private function parseCtr(mixed $raw): ?float
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        if (str_ends_with($value, '%')) {
            $number = filter_var(rtrim($value, '%'), FILTER_VALIDATE_FLOAT);

            return $number === false ? null : $number / 100;
        }

        $number = filter_var($value, FILTER_VALIDATE_FLOAT);

        return $number === false ? null : $number;
    }
}
