<?php

namespace App\Services\SearchConsole;

/**
 * @param  list<string>  $errors  righe scartate, con motivo, 1-indicizzate
 *                                rispetto al file originale (riga 1 = intestazione)
 */
readonly class SearchConsoleImportResult
{
    public function __construct(
        public int $imported,
        public int $matchedToArticle,
        public int $unmatched,
        public array $errors,
        public string $importBatch,
    ) {}
}
