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

    protected $description = 'Fotografa le categorie non ancora pubbliche che rischiano di aprirsi incomplete (sola lettura, sicuro in produzione)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Prompt 4 (pianificazione categorie); esteso dal Cantiere 13 del
        programma Kairus 100 cantieri per coprire anche le bozze, non solo
        le categorie programmate (stessa estensione già applicata dal
        Cantiere 12 alla checklist nell'elenco admin). Esclusivamente in
        lettura: nessuna scrittura su database, nessuna categoria
        pubblicata o modificata — sicuro da eseguire in qualunque momento,
        anche in produzione.

        Per ogni categoria non ancora pubblicamente visibile (bozza,
        programmata o disattivata — vedi Category::isPubliclyVisible()),
        verifica se rischia di aprirsi incompleta quando verrà attivata o
        quando la data di pubblicazione (Europe/Rome) sarà raggiunta:
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
        $categories = Category::ordered()
            ->get()
            ->reject(fn (Category $category) => $category->isPubliclyVisible())
            ->values();

        $reports = $categories->map(fn (Category $category) => [
            'category_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'status' => $category->status,
            'is_active' => $category->is_active,
            // Riusa la stessa etichetta già mostrata nell'elenco admin
            // (Category::effectiveVisibilityLabel()): "status" da solo non
            // basta perché una categoria disattivata (is_active=false) può
            // avere qualunque status — senza questo, una categoria
            // programmata o pubblicata ma disattivata verrebbe presentata
            // come se stesse per aprirsi, mentre è semplicemente spenta
            // (finding Codex su questa PR).
            'visibility_label' => $category->effectiveVisibilityLabel(),
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
        $this->line('<fg=cyan;options=bold>READINESS CATEGORIE NON PUBBLICHE — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        if ($reports->isEmpty()) {
            $this->info('Nessuna categoria non pubblica al momento.');

            return;
        }

        foreach ($reports as $r) {
            $this->line("<fg=cyan;options=bold>#{$r['category_id']} — {$r['name']}</> (<fg=gray>{$r['slug']}</>)");

            if ($r['visibility_label'] === 'Programmata') {
                $this->line("  Programmata per: {$r['scheduled_at']} (Europe/Rome)");
            } else {
                $this->line("  Stato: {$r['visibility_label']}");
            }

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
        $this->line('  Categorie non pubbliche: '.$reports->count());
        $this->line("  Con almeno una criticità: {$withFindings}");
    }
}
