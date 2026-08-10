<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\EditorialQuality\EditorialQualityAuditService;
use App\Services\EditorialQuality\EditorialQualityAuditSummary;
use App\Services\EditorialQuality\EditorialQualityCheckResult;
use App\Services\EditorialQuality\EditorialQualityReport;
use Illuminate\Console\Command;

class EditorialQualityAuditCommand extends Command
{
    protected $signature = 'content:quality-audit
        {--article= : Limita l\'audit a un singolo articolo (ID)}
        {--status= : Limita l\'audit agli articoli con questo stato (draft, review, scheduled, published)}
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Fotografa la completezza editoriale degli articoli (Editorial Quality Gate), senza modificare nulla';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Editorial Quality Gate V1 — audit READ-ONLY: nessuna scrittura su
        database, nessun campo modificato — sicuro da eseguire in
        qualunque momento, anche in produzione.

        Verifica la COMPLETEZZA EDITORIALE di ogni articolo (titolo,
        corpo, cover, alt, SEO, struttura, fonti, collegamenti interni,
        coerenza di pubblicazione) — MAI l'accuratezza scientifica del
        contenuto, che nessun controllo automatico può giudicare.

        Ogni articolo riceve un livello:
          - READY — tutti i controlli applicabili superati;
          - ATTENTION — nessun controllo essenziale fallito, ma almeno un
            avviso da guardare;
          - INCOMPLETE — almeno un controllo essenziale non superato.

        Opzioni
        -------
        --article=   Limita l'audit a un singolo articolo (ID)
        --status=    Limita l'audit agli articoli con questo stato
        --json       Output JSON invece del report testuale
        HELP;

    public function handle(EditorialQualityAuditService $auditService): int
    {
        $articleId = $this->option('article') !== null ? (int) $this->option('article') : null;
        $status = $this->option('status');

        $summary = $auditService->audit($articleId, $status);

        if ($this->option('json')) {
            $this->line((string) json_encode($this->toJson($summary), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderTextReport($summary);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(EditorialQualityAuditSummary $summary): array
    {
        return [
            'summary' => [
                'analyzed' => $summary->analyzed,
                'ready' => $summary->readyCount,
                'attention' => $summary->attentionCount,
                'incomplete' => $summary->incompleteCount,
                'most_frequent_issues' => $summary->mostFrequentIssues,
            ],
            'articles' => array_map(fn (array $entry) => [
                'id' => $entry['article']->id,
                'title' => $entry['article']->title,
                'status' => $entry['article']->status,
                'level' => $entry['report']->level(),
                'passed' => $entry['report']->passedCount(),
                'applicable' => $entry['report']->applicableCount(),
                'checks' => array_map(fn (EditorialQualityCheckResult $r) => [
                    'code' => $r->code,
                    'label' => $r->label,
                    'status' => $r->status,
                    'importance' => $r->importance,
                    'category' => $r->category,
                    'message' => $r->message,
                ], $entry['report']->results),
            ], $summary->entries),
        ];
    }

    private function renderTextReport(EditorialQualityAuditSummary $summary): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>EDITORIAL QUALITY AUDIT — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata; verifica completezza editoriale, non accuratezza scientifica)');
        $this->newLine();

        $this->line("Analizzati: {$summary->analyzed}");
        $this->line("Ready: {$summary->readyCount}");
        $this->line("Attention: {$summary->attentionCount}");
        $this->line("Incomplete: {$summary->incompleteCount}");
        $this->newLine();

        $this->line('<fg=cyan;options=bold>Problemi più frequenti</>');

        if ($summary->mostFrequentIssues === []) {
            $this->line('  Nessuno.');
        } else {
            foreach (array_slice($summary->mostFrequentIssues, 0, 10) as $issue) {
                $this->line("  - {$issue['label']}: {$issue['count']}");
            }
        }

        $flagged = array_filter($summary->entries, fn (array $e) => $e['report']->level() !== EditorialQualityReport::LEVEL_READY);

        if ($flagged !== []) {
            $this->newLine();
            $this->line('<fg=cyan;options=bold>ARTICOLI DA VERIFICARE</>');

            foreach ($flagged as $entry) {
                /** @var Article $article */
                $article = $entry['article'];
                /** @var EditorialQualityReport $report */
                $report = $entry['report'];

                $this->line("  #{$article->id} {$article->title} — {$report->levelLabel()} ({$report->passedCount()}/{$report->applicableCount()})");
            }
        }

        $this->newLine();
    }
}
