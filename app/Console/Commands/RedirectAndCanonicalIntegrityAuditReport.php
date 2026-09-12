<?php

namespace App\Console\Commands;

use App\Services\PublicPages\RedirectAndCanonicalIntegrityAudit;
use Illuminate\Console\Command;

/**
 * Cantiere 23 (programma 100-cantieri Kairus). Espone
 * App\Services\PublicPages\RedirectAndCanonicalIntegrityAudit da riga di
 * comando: verifica i vecchi slug articolo con redirect registrato e il
 * canonical di ogni articolo (limitato ai più recenti per default),
 * categoria e percorso realmente raggiungibili. Sola lettura, non è un
 * gate di rilascio.
 */
class RedirectAndCanonicalIntegrityAuditReport extends Command
{
    protected $signature = 'pages:redirect-canonical-audit
        {--limit=50 : Numero massimo di articoli (i più recenti) da verificare per il canonical}
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Verifica i redirect di vecchi slug articolo e la coerenza del canonical su ogni articolo/categoria/percorso raggiungibile. Solo lettura.';

    public function handle(RedirectAndCanonicalIntegrityAudit $audit): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $redirects = $audit->auditRedirects();
        $canonicals = $audit->auditCanonicalConsistency($limit);

        $anyFindings = $this->hasAnyFindings($redirects) || $this->hasAnyFindings($canonicals);

        if ($this->option('json')) {
            $this->line(json_encode(['redirects' => $redirects, 'canonical_consistency' => $canonicals], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $anyFindings ? self::FAILURE : self::SUCCESS;
        }

        $this->renderRedirects($redirects);
        $this->renderCanonicals($canonicals, $limit);

        return $anyFindings ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{old_slug: string, article_id: int, http_status: int, findings: list<string>}>  $redirects
     */
    private function renderRedirects(array $redirects): void
    {
        if ($redirects === []) {
            $this->info('Nessun vecchio slug articolo con redirect registrato.');

            return;
        }

        $this->table(
            ['Vecchio slug', 'Articolo #', 'Stato HTTP', 'Finding'],
            array_map(fn (array $r) => [
                $r['old_slug'],
                $r['article_id'],
                $r['http_status'],
                $r['findings'] === [] ? '—' : implode(' | ', $r['findings']),
            ], $redirects)
        );

        $broken = array_values(array_filter($redirects, fn (array $r) => $r['findings'] !== []));
        if ($broken !== []) {
            $this->newLine();
            $this->warn(sprintf('%d vecchio/i slug con redirect incoerente: %s.', count($broken), implode(', ', array_column($broken, 'old_slug'))));
        } else {
            $this->newLine();
            $this->info('Tutti i redirect registrati sono coerenti.');
        }
    }

    /**
     * @param  list<array{type: string, url: string, http_status: int, findings: list<string>}>  $canonicals
     */
    private function renderCanonicals(array $canonicals, int $limit): void
    {
        $this->newLine();
        $this->line("Coerenza canonical (articoli limitati ai {$limit} più recenti):");

        $this->table(
            ['Tipo', 'URL', 'Stato HTTP', 'Finding'],
            array_map(fn (array $c) => [
                $c['type'],
                $c['url'],
                $c['http_status'],
                $c['findings'] === [] ? '—' : implode(' | ', $c['findings']),
            ], $canonicals)
        );

        $broken = array_values(array_filter($canonicals, fn (array $c) => $c['findings'] !== []));
        if ($broken !== []) {
            $this->newLine();
            $this->warn(sprintf('%d pagina/e con canonical incoerente o stato HTTP inatteso.', count($broken)));
        } else {
            $this->newLine();
            $this->info('Nessuna incoerenza rilevata tra le pagine verificate.');
        }
    }

    /**
     * @param  list<array{findings: list<string>}>  $entries
     */
    private function hasAnyFindings(array $entries): bool
    {
        foreach ($entries as $entry) {
            if ($entry['findings'] !== []) {
                return true;
            }
        }

        return false;
    }
}
