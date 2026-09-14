<?php

namespace App\Console\Commands;

use App\Services\ContentSourcesRadarService;
use Illuminate\Console\Command;

/**
 * Cantiere 84 (programma "100 cantieri Kairus"): la metà "comando" del
 * radar fonti interno — espone in sola lettura ContentSourcesRadarService,
 * stesso pattern già in uso per content-clusters:publication-readiness
 * (Cantiere 47) e category:publication-audit (Cantieri 13-14). Nessuna
 * scrittura, nessun blocco: un editore legge il profilo aggregato delle
 * fonti citate sul sito pubblicato e decide da sé se serve diversificare.
 */
class ContentSourcesRadarAudit extends Command
{
    protected $signature = 'content-sources:radar
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Fotografa il profilo aggregato delle fonti citate negli articoli pubblicati (sola lettura, sicuro in produzione)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Cantiere 84 del programma Kairus 100 cantieri: a differenza
        dell'audit già esistente per un singolo articolo
        (EditorialQualityChecker::sourcesCheck()), questo comando guarda
        l'insieme degli articoli pubblicati e riporta quanti citano
        almeno una fonte, quanti nessuna, e quali domini ricorrono più
        spesso — un segnale editoriale su diversificazione delle fonti,
        mai un blocco o una scrittura.

        Opzioni
        -------
        --json    Output JSON invece del report testuale
        HELP;

    public function handle(ContentSourcesRadarService $radar): int
    {
        $report = $radar->report();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderTextReport($report);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderTextReport(array $report): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>RADAR FONTI — ARTICOLI PUBBLICATI KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        $this->line("Articoli pubblicati: {$report['total_published_articles']}");
        $this->line("  Con almeno una fonte: {$report['articles_with_sources']}");
        $this->line("  <fg=yellow>Senza alcuna fonte: {$report['articles_without_sources']}</>");
        $this->line("  Con solo fonti testuali (nessun link riconosciuto): {$report['articles_with_only_text_sources']}");
        $this->newLine();

        $this->line("Domini distinti citati: {$report['distinct_domains']}");

        if ($report['top_domains'] === []) {
            $this->info('Nessun dominio citato al momento.');

            return;
        }

        $this->line('<fg=cyan;options=bold>Domini più citati (per numero di articoli distinti)</>');
        foreach ($report['top_domains'] as $entry) {
            $this->line("  {$entry['domain']} — {$entry['article_count']} articolo/i");
        }
    }
}
