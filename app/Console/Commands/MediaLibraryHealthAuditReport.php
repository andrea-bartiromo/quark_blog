<?php

namespace App\Console\Commands;

use App\Services\MediaLibraryHealthAudit;
use Illuminate\Console\Command;

/**
 * Cantiere 26 (programma 100-cantieri Kairus). Espone
 * App\Services\MediaLibraryHealthAudit da riga di comando: testo
 * alternativo, credito/fonte, file mancanti su disco, peso elevato e
 * formato non ottimale per ogni immagine della Libreria media. Sola
 * lettura, non e' un gate di rilascio.
 */
class MediaLibraryHealthAuditReport extends Command
{
    protected $signature = 'media:health-audit
        {--max-size= : Soglia in byte oltre la quale un file è segnalato come "peso elevato" (default: config(media.audit_max_size_bytes))}
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Verifica testo alternativo, credito/fonte, file mancanti, peso e formato di ogni immagine della Libreria media. Solo lettura.';

    public function handle(MediaLibraryHealthAudit $audit): int
    {
        $maxSize = $this->option('max-size') !== null ? (int) $this->option('max-size') : null;
        $result = $audit->audit($maxSize);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->hasAnyFindings($result) ? self::FAILURE : self::SUCCESS;
        }

        $broken = array_values(array_filter($result['rows'], fn (array $r) => $r['findings'] !== []));

        if ($broken === []) {
            $this->info("Nessun problema rilevato su {$result['analyzed']} immagini analizzate.");

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'File', 'Finding'],
            array_map(fn (array $r) => [$r['id'], $r['filename'], implode(' | ', $r['findings'])], $broken)
        );

        $this->newLine();
        $this->warn(sprintf(
            '%d immagine/i su %d con almeno un finding — alt: %d, credito: %d, file mancante: %d, peso: %d, formato: %d.',
            count($broken),
            $result['analyzed'],
            $result['missing_alt'],
            $result['missing_credit'],
            $result['missing_file'],
            $result['oversized'],
            $result['non_optimal_format'],
        ));

        return self::FAILURE;
    }

    /**
     * @param  array{rows: list<array{findings: list<string>}>}  $result
     */
    private function hasAnyFindings(array $result): bool
    {
        foreach ($result['rows'] as $row) {
            if ($row['findings'] !== []) {
                return true;
            }
        }

        return false;
    }
}
