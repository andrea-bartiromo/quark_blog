<?php

namespace App\Console\Commands;

use App\Services\PublicPages\PublicPageSeoAudit;
use Illuminate\Console\Command;

/**
 * Cantiere 22 (programma 100-cantieri Kairus). Espone
 * App\Services\PublicPages\PublicPageSeoAudit da riga di comando: per
 * ogni tipo di pagina pubblica con un esempio disponibile, verifica
 * stato HTTP, title, meta description, canonical e JSON-LD (dove
 * atteso). Sola lettura: ogni richiesta è un GET in-process, non
 * modifica mai alcun contenuto e non è un gate di rilascio.
 */
class PublicPageSeoAuditReport extends Command
{
    protected $signature = 'pages:seo-audit {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Verifica stato HTTP/canonical/title/description/JSON-LD per ogni tipo di pagina pubblica. Solo lettura, non modifica mai alcun contenuto.';

    public function handle(PublicPageSeoAudit $audit): int
    {
        $pages = $audit->audit();

        if ($this->option('json')) {
            $this->line(json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Tipo', 'Verificata', 'Stato HTTP', 'Canonical', 'JSON-LD', 'Finding'],
            array_map(fn (array $page) => [
                $page['key'],
                $page['checked'] ? 'sì' : '— nessun esempio disponibile —',
                $page['http_status'] ?? '—',
                $page['canonical'] !== null ? 'presente' : ($page['checked'] ? 'assente' : '—'),
                $page['json_ld_blocks'] !== null ? $page['json_ld_blocks'] : '—',
                $page['findings'] === [] ? '—' : implode(' | ', $page['findings']),
            ], $pages)
        );

        $withFindings = array_values(array_filter($pages, fn (array $page) => $page['findings'] !== []));
        $unchecked = array_values(array_filter($pages, fn (array $page) => ! $page['checked']));

        if ($withFindings !== []) {
            $this->newLine();
            $this->warn(sprintf(
                '%d tipo/i di pagina segnalano almeno un problema: %s.',
                count($withFindings),
                implode(', ', array_column($withFindings, 'key'))
            ));
        }

        if ($unchecked !== []) {
            $this->newLine();
            $this->line(sprintf(
                '%d tipo/i di pagina non verificati per assenza di un esempio pubblico in questo ambiente: %s.',
                count($unchecked),
                implode(', ', array_column($unchecked, 'key'))
            ));
        }

        if ($withFindings === []) {
            $this->newLine();
            $this->info('Nessun problema rilevato tra le pagine verificate.');
        }

        return self::SUCCESS;
    }
}
