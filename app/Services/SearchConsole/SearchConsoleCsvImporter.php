<?php

namespace App\Services\SearchConsole;

use App\Models\SearchConsoleQuery;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa dati CSV di Google Search Console.
 *
 * Formati supportati:
 *
 * 1. Formato combinato storico:
 *    query,page,clicks,impressions,ctr,position
 *
 * 2. Export standard "Query":
 *    Query più frequenti,Clic,Impressioni,CTR,Posizione
 *
 * L'export Query standard non contiene la dimensione pagina. In quel caso
 * page_url resta vuoto e article_id resta null: non viene inventata alcuna
 * associazione query -> pagina che Search Console non abbia realmente
 * fornito.
 *
 * L'import è idempotente per periodo: un secondo import dello stesso
 * intervallo sostituisce le righe precedenti.
 */
class SearchConsoleCsvImporter
{
    private const MAX_ROWS = 50000;

    /**
     * Alias accettati per gli header.
     *
     * Gli alias italiani corrispondono all'export reale Search Console
     * scaricato dall'interfaccia italiana.
     */
    private const HEADER_ALIASES = [
        'query' => [
            'query',
            'query più frequenti',
            'query piu frequenti',
        ],
        'page' => [
            'page',
            'pagina',
            'pagine principali',
        ],
        'clicks' => [
            'clicks',
            'clic',
        ],
        'impressions' => [
            'impressions',
            'impressioni',
        ],
        'ctr' => [
            'ctr',
        ],
        'position' => [
            'position',
            'posizione',
        ],
    ];

    public function __construct(
        private readonly SearchConsoleQueryArticleMatcher $matcher
    ) {}

    public function import(
        string $filePath,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd
    ): SearchConsoleImportResult {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return new SearchConsoleImportResult(
                0,
                0,
                0,
                ['Impossibile aprire il file.'],
                ''
            );
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                return new SearchConsoleImportResult(
                    0,
                    0,
                    0,
                    ['File vuoto.'],
                    ''
                );
            }

            if (isset($header[0])) {
                $header[0] = $this->stripBom((string) $header[0]);
            }

            $columnIndex = $this->resolveColumnIndex($header);

            if ($columnIndex === null) {
                return new SearchConsoleImportResult(
                    0,
                    0,
                    0,
                    [
                        'Intestazioni non riconosciute. Sono supportati '
                        .'l\'export Query standard di Google Search Console '
                        .'e il formato combinato query + page.',
                    ],
                    ''
                );
            }

            $importBatch = (string) Str::uuid();
            $rows = [];
            $errors = [];
            $matched = 0;
            $lineNumber = 1;

            while (($record = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if (count($rows) >= self::MAX_ROWS) {
                    $errors[] =
                        "Riga {$lineNumber}: limite di "
                        .self::MAX_ROWS
                        .' righe per import raggiunto, resto del file ignorato.';

                    break;
                }

                $parsed = $this->parseRow(
                    $record,
                    $columnIndex
                );

                if ($parsed === null) {
                    $errors[] =
                        "Riga {$lineNumber}: valori non validi, riga scartata.";

                    continue;
                }

                $article = null;

                if ($parsed['page_url'] !== '') {
                    $article = $this->matcher->match(
                        $parsed['page_url']
                    );
                }

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
                return new SearchConsoleImportResult(
                    0,
                    0,
                    0,
                    [
                        ...$errors,
                        'Nessuna riga valida trovata.',
                    ],
                    ''
                );
            }

            DB::transaction(
                function () use (
                    $rows,
                    $periodStart,
                    $periodEnd
                ) {
                    SearchConsoleQuery::query()
                        ->whereDate(
                            'period_start',
                            $periodStart->toDateString()
                        )
                        ->whereDate(
                            'period_end',
                            $periodEnd->toDateString()
                        )
                        ->delete();

                    foreach (array_chunk($rows, 500) as $chunk) {
                        SearchConsoleQuery::query()->insert($chunk);
                    }
                }
            );

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

    private function stripBom(string $value): string
    {
        return str_starts_with(
            $value,
            "\xEF\xBB\xBF"
        )
            ? substr($value, 3)
            : $value;
    }

    /**
     * @return array<string,int>|null
     */
    private function resolveColumnIndex(array $header): ?array
    {
        $normalized = array_map(
            fn ($value) => $this->normalizeHeader(
                (string) $value
            ),
            $header
        );

        $index = [];

        foreach (
            ['query', 'clicks', 'impressions', 'ctr', 'position']
            as $column
        ) {
            $position = $this->findHeaderPosition(
                $normalized,
                self::HEADER_ALIASES[$column]
            );

            if ($position === null) {
                return null;
            }

            $index[$column] = $position;
        }

        /*
         * "page" è deliberatamente opzionale.
         *
         * L'export standard Query di Search Console non contiene la pagina.
         */
        $pagePosition = $this->findHeaderPosition(
            $normalized,
            self::HEADER_ALIASES['page']
        );

        if ($pagePosition !== null) {
            $index['page'] = $pagePosition;
        }

        return $index;
    }

    private function findHeaderPosition(
        array $normalizedHeader,
        array $aliases
    ): ?int {
        foreach ($aliases as $alias) {
            $position = array_search(
                $this->normalizeHeader($alias),
                $normalizedHeader,
                true
            );

            if ($position !== false) {
                return $position;
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtolower(
            trim($this->stripBom($value))
        );
    }

    /**
     * @return array{
     *   query:string,
     *   page_url:string,
     *   clicks:int,
     *   impressions:int,
     *   ctr:float,
     *   position:float
     * }|null
     */
    private function parseRow(
        array $record,
        array $columnIndex
    ): ?array {
        $query = trim(
            (string) (
                $record[$columnIndex['query']]
                ?? ''
            )
        );

        $pageUrl = '';

        if (isset($columnIndex['page'])) {
            $pageUrl = trim(
                (string) (
                    $record[$columnIndex['page']]
                    ?? ''
                )
            );
        }

        if ($query === '') {
            return null;
        }

        /*
         * Nel formato combinato, se la colonna page esiste deve avere
         * realmente un valore. Nell'export Query standard invece la colonna
         * non esiste e page_url resta deliberatamente vuoto.
         */
        if (
            isset($columnIndex['page'])
            && $pageUrl === ''
        ) {
            return null;
        }

        $clicks = filter_var(
            $record[$columnIndex['clicks']] ?? null,
            FILTER_VALIDATE_INT
        );

        $impressions = filter_var(
            $record[$columnIndex['impressions']] ?? null,
            FILTER_VALIDATE_INT
        );

        $ctr = $this->parseCtr(
            $record[$columnIndex['ctr']] ?? null
        );

        $position = filter_var(
            $this->normalizeLocaleDecimal(
                $record[$columnIndex['position']] ?? null
            ),
            FILTER_VALIDATE_FLOAT
        );

        if (
            $clicks === false
            || $impressions === false
            || $ctr === null
            || $position === false
        ) {
            return null;
        }

        if (
            $clicks < 0
            || $impressions < 0
            || $ctr < 0
            || $position < 0
        ) {
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

    private function parseCtr(mixed $raw): ?float
    {
        if (
            ! is_string($raw)
            && ! is_numeric($raw)
        ) {
            return null;
        }

        $value = $this->normalizeLocaleDecimal(
            trim((string) $raw)
        );

        if (str_ends_with($value, '%')) {
            $number = filter_var(
                rtrim($value, '%'),
                FILTER_VALIDATE_FLOAT
            );

            return $number === false
                ? null
                : $number / 100;
        }

        $number = filter_var(
            $value,
            FILTER_VALIDATE_FLOAT
        );

        return $number === false
            ? null
            : $number;
    }

    private function normalizeLocaleDecimal(
        mixed $raw
    ): mixed {
        if (! is_string($raw)) {
            return $raw;
        }

        if (
            substr_count($raw, ',') === 1
            && ! str_contains($raw, '.')
        ) {
            return str_replace(',', '.', $raw);
        }

        return $raw;
    }
}
