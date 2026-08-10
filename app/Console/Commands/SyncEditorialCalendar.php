<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectDocument;
use App\Services\Editorial\EditorialCalendarLinkingResult;
use App\Services\Editorial\EditorialCalendarLinkingService;
use App\Services\Editorial\EditorialCalendarMatchingService;
use App\Services\Editorial\EditorialCalendarReconciliationEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncEditorialCalendar extends Command
{
    protected $signature = 'project:sync-editorial-calendar
        {--project= : Limita la sincronizzazione a un singolo progetto (ID)}
        {--execute : Applica davvero i collegamenti sicuri (default: sola analisi/dry-run, nessuna modifica)}';

    protected $description = 'Confronta il calendario editoriale di un progetto con gli articoli del CMS e collega automaticamente solo i match sicuri (default: sola analisi)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        FASE 3 della missione automazione Piano Editoriale. Per ogni
        progetto con un documento marcato is_editorial_calendar,
        confronta le voci del calendario con gli articoli reali del CMS
        (per titolo — vedi EditorialCalendarMatchingService) e collega
        automaticamente SOLO i match sicuri e non ambigui (titolo
        identico o identico dopo normalizzazione prudente, candidato
        unico, non gia' collegato).

        Non viene MAI:
        - creato, modificato o cancellato un articolo;
        - scollegato un articolo (nessun detach());
        - collegato un match ambiguo o con piu' candidati: resta sempre
          e solo un suggerimento da revisionare a mano (vedi
          project:editorial-audit e la scheda del progetto);
        - modificato il documento di calendario stesso.

        Modalita dry-run (default)
        ---------------------------
        Sola lettura: mostra cosa verrebbe collegato senza scrivere
        nulla.

        Modalita --execute
        -------------------
        Applica i collegamenti sicuri tramite
        EditorialCalendarLinkingService::apply(), registrando ognuno in
        Cronologia con origine "Sync calendario"
        (ProjectActivityLog::SOURCE_EDITORIAL_SYNC). Idempotente: una
        seconda esecuzione non ricollega ne' duplica nulla, perche' le
        voci gia' collegate non compaiono piu' tra i match sicuri.

        Opzioni
        -------
        --project=   Limita a un singolo progetto (ID). Senza questa
                     opzione, il comando elabora tutti i progetti con un
                     documento di calendario marcato.
        --execute    Applica i collegamenti (default: sola analisi)
        HELP;

    public function handle(EditorialCalendarLinkingService $linkingService): int
    {
        $execute = (bool) $this->option('execute');

        $projects = $this->resolveProjects();

        if ($projects === null) {
            return self::FAILURE;
        }

        if ($projects->isEmpty()) {
            $this->info('Nessun progetto con un documento di calendario editoriale marcato (is_editorial_calendar).');

            return self::SUCCESS;
        }

        $this->info($execute ? 'Modalita: ESEGUI collegamenti sicuri' : 'Modalita: sola analisi (dry-run)');
        $this->newLine();

        $totalLinked = 0;

        foreach ($projects as $project) {
            $result = $execute ? $linkingService->apply($project) : $linkingService->preview($project);

            $this->printProjectSummary($project, $result);

            $totalLinked += $result->linkedCount();
        }

        $this->newLine();

        if (! $execute) {
            $this->info('Dry-run completata: nessuna modifica applicata. Usa --execute per collegare i match sicuri.');
        } else {
            $this->info("Sincronizzazione completata: {$totalLinked} collegamento/i applicato/i.");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Project>|null null se
     *                                       --project punta a un progetto inesistente o senza calendario
     *                                       (errore stampato, exit FAILURE gestito dal chiamante).
     */
    private function resolveProjects()
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

        $projectIds = ProjectDocument::query()
            ->editorialCalendar()
            ->pluck('project_id')
            ->unique();

        return Project::query()->whereIn('id', $projectIds)->get();
    }

    private function printProjectSummary(Project $project, EditorialCalendarLinkingResult $result): void
    {
        $report = $result->report;

        $this->line("<fg=cyan;options=bold>Progetto #{$project->id} — {$project->title}</>");

        if ($report->parseErrors !== []) {
            $this->line('  Righe non interpretabili: '.count($report->parseErrors));
            foreach ($report->parseErrors as $error) {
                $this->line("    riga {$error->lineNumber}: {$error->reason}");
            }
        }

        $this->line('  Voci nel calendario: '.$report->totalEntries());
        $this->line('  Gia\' collegate: '.count($report->alreadyLinked()));
        $this->line('  '.($result->dryRun ? 'Collegabili in sicurezza' : 'Collegate ora').': '.$result->linkedCount());
        $this->line('  Nessun articolo corrispondente: '.count($report->missingArticles()));
        $this->line('  Richiedono revisione umana: '.count($report->requiringReview()));
        $this->line('  Discrepanze di data: '.count($report->dateDiscrepancies()));

        $statusMismatches = array_filter(
            $report->entries,
            fn (EditorialCalendarReconciliationEntry $e) => $e->discrepancyType === EditorialCalendarReconciliationEntry::DISCREPANCY_STATUS_MISMATCH
        );
        $this->line('  Discrepanze di stato: '.count($statusMismatches));

        if ($report->articlesOutsidePlan !== []) {
            $this->line('  Articoli collegati fuori piano: '.count($report->articlesOutsidePlan));
        }

        if ($result->linked !== []) {
            foreach ($result->linked as $linkedEntry) {
                $label = $linkedEntry->matchType === EditorialCalendarMatchingService::MATCH_EXACT ? 'esatto' : 'normalizzato';
                $this->line("    → #{$linkedEntry->entry->position} \"{$linkedEntry->entry->title}\" collegato (match {$label}) ad articolo #{$linkedEntry->article->id}");
            }
        }

        $this->newLine();
    }
}
