<?php

namespace App\Console\Commands;

use App\Services\Deploy\ReleaseRegistry;
use Illuminate\Console\Command;

/**
 * Prompt 7 (programma 100-prompt Kairus): comando diagnostico read-only,
 * mai mutante. Mostra la storia dei rilasci registrata da
 * release:record-registry (deployed da deploy.sh; verified/measured da
 * comandi/processi separati eseguiti in seguito) — la stessa
 * informazione che la roadmap operativa tiene oggi a mano in una tabella
 * Markdown (vedi docs/KAIRUS_TECHNICAL_ROADMAP_V14.md, "Principio di
 * stato"), qui letta dalla fonte automatica quando configurata.
 */
class ReleaseRegistryShow extends Command
{
    protected $signature = 'release:registry {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Mostra la storia dei rilasci registrata nel release registry (DEPLOY_RELEASE_REGISTRY_PATH). Solo lettura, non modifica mai nulla.';

    public function handle(ReleaseRegistry $registry): int
    {
        if (! $registry->isEnabled()) {
            if ($this->option('json')) {
                $this->line((string) json_encode(['enabled' => false, 'history' => []]));

                return self::SUCCESS;
            }

            $this->info('DEPLOY_RELEASE_REGISTRY_PATH non è configurato: nessuna storia rilasci da mostrare. Non è un fallimento.');

            return self::SUCCESS;
        }

        $history = $registry->history();

        if ($this->option('json')) {
            $this->line((string) json_encode(['enabled' => true, 'history' => $history], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderTextReport($history);

        return self::SUCCESS;
    }

    /**
     * @param  list<array{revision:string,stages:array<string,string>}>  $history
     */
    private function renderTextReport(array $history): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>RELEASE REGISTRY — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        if ($history === []) {
            $this->info('Nessuna revisione registrata ancora.');

            return;
        }

        $this->table(
            ['Revisione', 'Deployed', 'Verified', 'Measured'],
            array_map(fn (array $entry) => [
                substr($entry['revision'], 0, 12),
                $entry['stages'][ReleaseRegistry::STAGE_DEPLOYED] ?? '—',
                $entry['stages'][ReleaseRegistry::STAGE_VERIFIED] ?? '—',
                $entry['stages'][ReleaseRegistry::STAGE_MEASURED] ?? '—',
            ], $history)
        );
    }
}
