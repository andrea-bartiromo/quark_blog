<?php

namespace App\Console\Commands;

use App\Services\PublicPages\PublicPageInventory;
use Illuminate\Console\Command;

/**
 * Cantiere 21 (programma 100-cantieri Kairus). Espone
 * App\Services\PublicPages\PublicPageInventory da riga di comando: un
 * catalogo di sola lettura di ogni tipo di pagina pubblica di Kairus,
 * con un URL di esempio realmente raggiungibile in questo ambiente
 * (quando esiste). Non crea, modifica o pubblica mai alcun contenuto.
 */
class PublicPageInventoryReport extends Command
{
    protected $signature = 'pages:inventory {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Elenca ogni tipo di pagina pubblica di Kairus con un URL di esempio realmente raggiungibile. Solo lettura, non modifica mai alcun contenuto.';

    public function handle(PublicPageInventory $inventory): int
    {
        $pages = $inventory->pages();

        if ($this->option('json')) {
            $this->line(json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Tipo', 'Etichetta', 'Route', 'Natura', 'URL di esempio'],
            array_map(fn (array $page) => [
                $page['key'],
                $page['label'],
                $page['route_name'],
                $page['kind'] === 'static' ? 'statica' : 'dinamica',
                $page['sample_url'] ?? '— nessun esempio pubblico disponibile —',
            ], $pages)
        );

        $missing = array_values(array_filter($pages, fn (array $page) => $page['sample_url'] === null));

        if ($missing !== []) {
            $this->newLine();
            $this->warn(sprintf(
                '%d tipo/i di pagina dinamica non ha/hanno alcun esempio pubblico disponibile in questo ambiente (nessun record pubblicato trovato): %s.',
                count($missing),
                implode(', ', array_column($missing, 'key'))
            ));
        }

        return self::SUCCESS;
    }
}
