<?php

namespace App\Console\Commands;

use App\Services\LinkHealth\LinkReachabilityAuditService;
use Illuminate\Console\Command;

/**
 * Cantiere 25 (programma 100-cantieri Kairus). Espone
 * App\Services\LinkHealth\LinkReachabilityAuditService da riga di
 * comando: collegamenti interni (diversi da /articolo/, già coperti da
 * content:internal-link-audit) verso categoria/percorso/pagine
 * statiche, ed esterni verso siti di terzi. Sola lettura, non è un
 * gate di rilascio.
 */
class LinkReachabilityAuditReport extends Command
{
    protected $signature = 'content:link-reachability-audit
        {--limit=50 : Numero massimo di articoli (i più recenti) da analizzare}
        {--check-external : Esegue anche una vera richiesta HTTP verso ogni link esterno trovato (lento, dipende da siti di terzi: MAI di default)}
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Verifica i collegamenti interni (oltre /articolo/) ed esterni trovati nel corpo degli articoli più recenti. Solo lettura.';

    public function handle(LinkReachabilityAuditService $audit): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $checkExternal = (bool) $this->option('check-external');

        $result = $audit->audit($limit, $checkExternal);

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->hasAnyFindings($result) ? self::FAILURE : self::SUCCESS;
        }

        $this->renderInternal($result['internal']);
        $this->renderExternal($result['external'], $checkExternal);

        return $this->hasAnyFindings($result) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{url: string, articles: list<string>, http_status: ?int, findings: list<string>}>  $rows
     */
    private function renderInternal(array $rows): void
    {
        if ($rows === []) {
            $this->info('Nessun collegamento interno (oltre /articolo/) trovato negli articoli analizzati.');

            return;
        }

        $this->table(
            ['URL interno', 'Articoli', 'Stato HTTP', 'Finding'],
            array_map(fn (array $r) => [
                $r['url'],
                implode(', ', $r['articles']),
                $r['http_status'] ?? '—',
                $r['findings'] === [] ? '—' : implode(' | ', $r['findings']),
            ], $rows)
        );

        $broken = array_values(array_filter($rows, fn (array $r) => $r['findings'] !== []));
        $this->newLine();
        $this->{$broken === [] ? 'info' : 'warn'}(
            $broken === []
                ? 'Tutti i collegamenti interni sono raggiungibili.'
                : sprintf('%d collegamento/i interno/i non raggiungibile/i.', count($broken))
        );
    }

    /**
     * @param  list<array{url: string, articles: list<string>, reachable: ?bool, findings: list<string>}>  $rows
     */
    private function renderExternal(array $rows, bool $checkExternal): void
    {
        $this->newLine();

        if ($rows === []) {
            $this->info('Nessun collegamento esterno trovato negli articoli analizzati.');

            return;
        }

        if (! $checkExternal) {
            $this->line(sprintf(
                '%d collegamento/i esterno/i trovato/i, non verificato/i (usa --check-external per una richiesta HTTP reale verso ciascuno):',
                count($rows)
            ));
            foreach ($rows as $r) {
                $this->line('  - '.$r['url'].' ('.implode(', ', $r['articles']).')');
            }

            return;
        }

        $this->table(
            ['URL esterno', 'Articoli', 'Raggiungibile', 'Finding'],
            array_map(fn (array $r) => [
                $r['url'],
                implode(', ', $r['articles']),
                $r['reachable'] ? 'sì' : 'no',
                $r['findings'] === [] ? '—' : implode(' | ', $r['findings']),
            ], $rows)
        );

        $broken = array_values(array_filter($rows, fn (array $r) => $r['findings'] !== []));
        $this->newLine();
        $this->{$broken === [] ? 'info' : 'warn'}(
            $broken === []
                ? 'Tutti i collegamenti esterni sono raggiungibili.'
                : sprintf('%d collegamento/i esterno/i non raggiungibile/i.', count($broken))
        );
    }

    /**
     * @param  array{internal: list<array{findings: list<string>}>, external: list<array{findings: list<string>}>}  $result
     */
    private function hasAnyFindings(array $result): bool
    {
        foreach ([...$result['internal'], ...$result['external']] as $entry) {
            if ($entry['findings'] !== []) {
                return true;
            }
        }

        return false;
    }
}
