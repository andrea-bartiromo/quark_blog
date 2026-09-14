<?php

namespace App\Console\Commands;

use App\Models\ContentCluster;
use App\Services\ContentClusters\PercorsoPublicationReadinessService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Cantiere 47 (programma "100 cantieri Kairus", dipende dal Cantiere 46):
 * la metà mancante del pattern già costruito per Category ai Cantieri
 * 13-14 (`category:publication-readiness`) — non riscrive la logica di
 * readiness, che esiste già ed è più matura di quella di Category
 * (`PercorsoPublicationReadinessService::evaluate()`, già usata
 * dall'admin di ContentCluster): questo comando espone solo la stessa
 * logica in un report di sola lettura, riusabile senza aprire l'admin.
 *
 * Scope diretto: audita anche il pacchetto "Mente e comportamento"
 * (Cantiere 46) e qualunque futuro pacchetto simile (Cantiere 51
 * "Scienza e metodo"), segnalando cosa manca prima che un editore lo
 * attivi — mai un blocco, mai una scrittura.
 */
class ContentClusterPublicationReadinessAudit extends Command
{
    protected $signature = 'content-clusters:publication-readiness
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Fotografa i Percorsi non ancora pubblici che rischiano di aprirsi incompleti (sola lettura, sicuro in produzione)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Cantiere 47 del programma Kairus 100 cantieri: la metà "comando"
        del pattern già costruito per Category ai Cantieri 13-14, qui
        applicata a ContentCluster (Percorsi) riusando la logica di
        readiness già esistente (PercorsoPublicationReadinessService,
        già in uso nell'admin dei Percorsi) invece di duplicarla.
        Esclusivamente in lettura: nessuna scrittura su database, nessun
        Percorso attivato o modificato — sicuro da eseguire in qualunque
        momento, anche in produzione.

        Per ogni Percorso non ancora pubblicamente visibile (inattivo o
        programmato nel futuro — vedi ContentCluster::isPubliclyVisible()),
        mostra lo stato di readiness (NOT READY / READY WITH WARNINGS /
        READY) e ogni singola criticità rilevata da
        PercorsoPublicationReadinessService — campi mancanti, pillar non
        pubblico, articoli mancanti, raccordi editoriali non compilati,
        eccetera.

        Nessuna di queste condizioni blocca l'attivazione: sono segnali
        editoriali per l'editore umano, non un errore che impedisce nulla.

        Opzioni
        -------
        --json    Output JSON invece del report testuale
        HELP;

    public function handle(PercorsoPublicationReadinessService $readiness): int
    {
        $clusters = ContentCluster::query()
            ->ordered()
            ->get()
            ->reject(fn (ContentCluster $cluster) => $cluster->isPubliclyVisible())
            ->values();

        $reports = $clusters->map(function (ContentCluster $cluster) use ($readiness) {
            // Un Percorso "Programmato" (is_active=true, publish_at
            // futuro) va valutato all'istante in cui aprirà davvero, non
            // ad adesso: senza questo, un pillar/articolo programmato per
            // pubblicarsi PRIMA di publish_at ma non ancora pubblico ORA
            // farebbe risultare il Percorso NOT READY per errori che si
            // saranno già risolti all'apertura (Codex, PR #605).
            $result = $readiness->evaluate($cluster, $cluster->publish_at);

            return [
                'content_cluster_id' => $cluster->id,
                'name' => $cluster->name,
                'slug' => $cluster->slug,
                'visibility_label' => $cluster->publicVisibilityLabel(),
                'lifecycle_status' => $cluster->lifecycle_status,
                'status' => $result['status'],
                'findings' => $result['findings']->map(fn (array $finding) => [
                    'code' => $finding['code'],
                    'severity' => $finding['severity'],
                    'message' => $finding['message'],
                ])->all(),
            ];
        })->values();

        if ($this->option('json')) {
            $this->line((string) json_encode($reports->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderTextReport($reports);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $reports
     */
    private function renderTextReport($reports): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>READINESS PERCORSI NON PUBBLICI — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        if ($reports->isEmpty()) {
            $this->info('Nessun Percorso non pubblico al momento.');

            return;
        }

        foreach ($reports as $r) {
            $this->line("<fg=cyan;options=bold>#{$r['content_cluster_id']} — {$r['name']}</> (<fg=gray>{$r['slug']}</>)");
            $this->line("  Stato: {$r['visibility_label']} — lifecycle: {$r['lifecycle_status']}");

            $statusColor = match ($r['status']) {
                'READY' => 'green',
                'READY WITH WARNINGS' => 'yellow',
                default => 'red',
            };
            $this->line("  Readiness: <fg={$statusColor};options=bold>{$r['status']}</>");

            if ($r['findings'] === []) {
                $this->line('  <fg=green>Nessuna criticità rilevata.</>');
            } else {
                foreach ($r['findings'] as $finding) {
                    $color = match ($finding['severity']) {
                        'ERROR' => 'red',
                        'WARNING' => 'yellow',
                        default => 'gray',
                    };
                    $this->line("  <fg={$color}>[{$finding['severity']}] {$finding['message']}</>");
                }
            }

            $this->newLine();
        }

        $withFindings = $reports->filter(fn ($r) => $r['findings'] !== [])->count();

        $this->line('<fg=cyan;options=bold>Riepilogo</>');
        $this->line('  Percorsi non pubblici: '.$reports->count());
        $this->line("  Con almeno una criticità: {$withFindings}");
    }
}
