<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\CategoryPublicationReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CategoryPublicationReadinessAudit extends Command
{
    protected $signature = 'category:publication-readiness
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Fotografa le categorie programmate che rischiano di aprirsi incomplete (sola lettura, sicuro in produzione)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Prompt 4 (pianificazione categorie). Esclusivamente in lettura:
        nessuna scrittura su database, nessuna categoria pubblicata o
        modificata — sicuro da eseguire in qualunque momento, anche in
        produzione.

        Per ogni categoria con status "scheduled" (Category::STATUS_SCHEDULED),
        verifica se rischia di aprirsi incompleta quando la data di
        pubblicazione (Europe/Rome) sarà raggiunta:
        - descrizione mancante;
        - immagine mancante;
        - colore badge mancante;
        - nessun articolo pubblicato o programmato assegnato alla categoria;
        - nessun Percorso collegato ad articoli della categoria.

        Nessuna di queste condizioni blocca la pubblicazione programmata:
        sono segnali editoriali, non un errore.

        Opzioni
        -------
        --json    Output JSON invece del report testuale
        HELP;

    public function handle(CategoryPublicationReadiness $readiness): int
    {
        $categories = Category::query()
            ->where('status', Category::STATUS_SCHEDULED)
            ->ordered()
            ->get();

        $reports = $categories->map(fn (Category $category) => [
            'category_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'scheduled_at' => optional($category->publishedAtForEditors())->format('Y-m-d H:i'),
            'findings' => $readiness->evaluate($category)['findings'],
        ])->values();

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
        $this->line('<fg=cyan;options=bold>READINESS CATEGORIE PROGRAMMATE — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        if ($reports->isEmpty()) {
            $this->info('Nessuna categoria programmata al momento.');

            return;
        }

        foreach ($reports as $r) {
            $this->line("<fg=cyan;options=bold>#{$r['category_id']} — {$r['name']}</> (<fg=gray>{$r['slug']}</>)");
            $this->line("  Programmata per: {$r['scheduled_at']} (Europe/Rome)");

            if ($r['findings'] === []) {
                $this->line('  <fg=green>Nessuna criticità rilevata.</>');
            } else {
                foreach ($r['findings'] as $finding) {
                    $this->line('  <fg=yellow>'.CategoryPublicationReadiness::label($finding).'</>');
                }
            }

            $this->newLine();
        }

        $withFindings = $reports->filter(fn ($r) => $r['findings'] !== [])->count();

        $this->line('<fg=cyan;options=bold>Riepilogo</>');
        $this->line('  Categorie programmate: '.$reports->count());
        $this->line("  Con almeno una criticità: {$withFindings}");
    }
}
