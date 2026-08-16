<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\Editorial\EditorialCalendarProgress;
use App\Services\Editorial\EditorialCalendarReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class EditorialCalendarAudit extends Command
{
    protected $signature = 'project:editorial-audit
        {--project= : Limita l\'audit a un singolo progetto (ID)}
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Fotografa lo stato di riconciliazione tra i calendari editoriali e gli articoli reali del CMS, senza modificare nulla (sola lettura, sicuro in produzione)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        FASE 10 della missione automazione Piano Editoriale. Esclusivamente
        in lettura: nessuna scrittura su database, nessun collegamento
        applicato, nessun file modificato — sicuro da eseguire in
        qualunque momento, anche in produzione. Per applicare i
        collegamenti sicuri usare project:sync-editorial-calendar
        (--execute).

        Per ogni progetto con un documento marcato is_editorial_calendar,
        calcola:
        - copertura (percentuale di voci con un articolo corrispondente,
          in qualunque stato) e percentuale effettivamente pubblicata —
          due numeri distinti, mai un'unica percentuale fuorviante;
        - conteggio per stato reale (pubblicato/programmato/in
          lavorazione);
        - voci senza alcun articolo corrispondente;
        - voci che richiedono una decisione umana (match ambiguo);
        - discrepanze di data e di stato dichiarato;
        - quante voci sarebbero collegabili in sicurezza da
          project:sync-editorial-calendar;
        - articoli collegati al progetto ma non (più) presenti nel piano;
        - righe del documento non interpretabili (errori di parsing).

        Opzioni
        -------
        --project=   Limita a un singolo progetto (ID)
        --json       Output JSON invece del report testuale
        HELP;

    public function handle(EditorialCalendarReconciliationService $reconciliationService): int
    {
        $projects = $this->resolveProjects();

        if ($projects === null) {
            return self::FAILURE;
        }

        $projectReports = $projects->map(fn (Project $project) => $this->auditProject($project, $reconciliationService))->all();

        if ($this->option('json')) {
            $this->line((string) json_encode(array_values($projectReports), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderTextReport($projectReports);

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Project>|null null se --project punta a un
     *                                       progetto inesistente o senza calendario (errore stampato).
     */
    private function resolveProjects(): ?Collection
    {
        if ($projectId = $this->option('project')) {
            $project = Project::find((int) $projectId);

            if (! $project) {
                $this->error("Nessun progetto trovato con ID {$projectId}.");

                return null;
            }

            if (! $project->editorialCalendarDocument()) {
                $this->error("Il progetto #{$projectId} non ha un documento di calendario editoriale marcato (is_editorial_calendar).");

                return null;
            }

            return collect([$project]);
        }

        $projectIds = ProjectDocument::query()->editorialCalendar()->pluck('project_id')->unique();

        return Project::query()->whereIn('id', $projectIds)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditProject(Project $project, EditorialCalendarReconciliationService $reconciliationService): array
    {
        $report = $reconciliationService->reconcile($project);
        $progress = EditorialCalendarProgress::fromReport($report);

        return [
            'project_id' => $project->id,
            'project_title' => $project->title,
            'total_planned' => $progress->totalPlanned,
            'coverage_percent' => $progress->coveragePercent,
            'published_percent' => $progress->publishedPercent,
            'published_count' => $progress->publishedCount,
            'scheduled_count' => $progress->scheduledCount,
            'in_progress_count' => $progress->inProgressCount,
            'missing_article_count' => $progress->missingArticleCount,
            'needs_review_count' => $progress->needsReviewCount,
            'date_discrepancy_count' => count($report->dateDiscrepancies()),
            'status_mismatch_count' => count($report->statusMismatches()),
            'safe_to_auto_link_count' => count($report->safeToAutoLink()),
            'already_linked_count' => count($report->alreadyLinked()),
            'articles_outside_plan_count' => count($report->articlesOutsidePlan),
            'parse_errors' => array_map(
                fn ($e) => ['line' => $e->lineNumber, 'reason' => $e->reason],
                $report->parseErrors
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $projectReports
     */
    private function renderTextReport(array $projectReports): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>AUDIT PIANO EDITORIALE — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        if ($projectReports === []) {
            $this->info('Nessun progetto con un documento di calendario editoriale marcato (is_editorial_calendar).');

            return;
        }

        foreach ($projectReports as $r) {
            $this->line("<fg=cyan;options=bold>Progetto #{$r['project_id']} — {$r['project_title']}</>");
            $this->line("  Voci pianificate: {$r['total_planned']}");
            $this->line("  Copertura (con articolo): {$r['coverage_percent']}%");
            $this->line("  Pubblicato: {$r['published_percent']}%");
            $this->line("  Pubblicato/Programmato/In lavorazione: {$r['published_count']}/{$r['scheduled_count']}/{$r['in_progress_count']}");
            $this->line("  Senza articolo corrispondente: {$r['missing_article_count']}");
            $this->line("  Richiedono revisione umana: {$r['needs_review_count']}");
            $this->line("  Discrepanze di data: {$r['date_discrepancy_count']}");
            $this->line("  Discrepanze di stato: {$r['status_mismatch_count']}");
            $this->line("  Collegabili in sicurezza (project:sync-editorial-calendar): {$r['safe_to_auto_link_count']}");
            $this->line("  Già collegati: {$r['already_linked_count']}");

            if ($r['articles_outside_plan_count'] > 0) {
                $this->line("  Articoli collegati fuori piano: {$r['articles_outside_plan_count']}");
            }

            if ($r['parse_errors'] !== []) {
                $this->line('  Righe non interpretabili: '.count($r['parse_errors']));
                foreach ($r['parse_errors'] as $error) {
                    $this->line("    riga {$error['line']}: {$error['reason']}");
                }
            }

            $this->newLine();
        }

        $totalProjects = count($projectReports);
        $totalPlanned = array_sum(array_column($projectReports, 'total_planned'));
        $totalMissing = array_sum(array_column($projectReports, 'missing_article_count'));
        $totalNeedsReview = array_sum(array_column($projectReports, 'needs_review_count'));
        $totalSafeToLink = array_sum(array_column($projectReports, 'safe_to_auto_link_count'));

        $this->line('<fg=cyan;options=bold>Riepilogo</>');
        $this->line("  Progetti con calendario: {$totalProjects}");
        $this->line("  Voci pianificate totali: {$totalPlanned}");
        $this->line("  Senza articolo corrispondente: {$totalMissing}");
        $this->line("  Richiedono revisione umana: {$totalNeedsReview}");
        $this->line("  Collegabili in sicurezza: {$totalSafeToLink}");
    }
}
