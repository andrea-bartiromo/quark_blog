<?php

namespace App\Console\Commands;

use App\Services\SearchConsole\SearchOpportunityDecisionService;
use Illuminate\Console\Command;

class SearchOpportunityOutcomeMeasurement extends Command
{
    protected $signature = 'search-opportunities:measure-outcomes';

    protected $description = 'Misura l\'esito a 28/90 giorni delle decisioni editoriali su opportunità di ricerca (sola lettura)';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Cantiere 4 (programma "Kairus Organic Discovery") — sola lettura:
        nessuna scrittura su articoli, nessuna chiamata esterna. Per ogni
        decisione editoriale la cui data di baseline è passata da almeno
        28 (o 90) giorni e non ancora misurata, cerca la stessa
        opportunità (stessa chiave tipo|query|pagina) nei dati Search
        Console attualmente disponibili e registra clic/impression/CTR/
        posizione osservati.

        Una decisione la cui opportunità non compare più nei dati attuali
        resta semplicemente non misurata (fail-closed): nessun valore
        indovinato o azzerato. Rieseguire questo comando periodicamente
        finché la misurazione non compare.
        HELP;

    public function handle(SearchOpportunityDecisionService $decisions): int
    {
        $result = $decisions->measureDueOutcomes();

        $this->line("Misurate a +28 giorni: {$result['measured_28d']}");
        $this->line("Misurate a +90 giorni: {$result['measured_90d']}");

        return self::SUCCESS;
    }
}
