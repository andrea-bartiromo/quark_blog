<?php

namespace App\Console\Commands;

use App\Services\PublicPages\WcagInternalAudit;
use Illuminate\Console\Command;

/**
 * Cantiere 29 (programma 100-cantieri Kairus). Espone
 * App\Services\PublicPages\WcagInternalAudit da riga di comando: verifica
 * lang/heading/landmark/skip-link/alt/nome-accessibile su ogni pagina
 * dell'inventario (Cantiere 21) con un esempio realmente raggiungibile.
 * Sola lettura, non è un gate di rilascio.
 */
class WcagInternalAuditReport extends Command
{
    protected $signature = 'pages:wcag-audit
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Verifica criteri WCAG statici (lang, heading, landmark, skip-link, alt, nome accessibile) su ogni pagina pubblica raggiungibile. Solo lettura.';

    public function handle(WcagInternalAudit $audit): int
    {
        $results = $audit->audit();
        $anyFindings = $this->hasAnyFindings($results);

        if ($this->option('json')) {
            $this->line(json_encode(['pages' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $anyFindings ? self::FAILURE : self::SUCCESS;
        }

        $this->table(
            ['Pagina', 'URL', 'Stato HTTP', 'Finding'],
            array_map(fn (array $r) => [
                $r['label'],
                $r['url'] ?? '— (nessun esempio disponibile)',
                $r['http_status'] ?? '—',
                $r['findings'] === [] ? '—' : implode(' | ', $r['findings']),
            ], $results)
        );

        $broken = array_values(array_filter($results, fn (array $r) => $r['findings'] !== []));
        if ($broken !== []) {
            $this->newLine();
            $this->warn(sprintf('%d pagina/e con almeno un finding WCAG: %s.', count($broken), implode(', ', array_column($broken, 'label'))));
        } else {
            $this->newLine();
            $this->info('Nessun finding WCAG rilevato sulle pagine verificate.');
        }

        return $anyFindings ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{findings: list<string>}>  $results
     */
    private function hasAnyFindings(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['findings'] !== []) {
                return true;
            }
        }

        return false;
    }
}
