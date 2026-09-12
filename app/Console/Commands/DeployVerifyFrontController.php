<?php

namespace App\Console\Commands;

use App\Services\Deploy\FrontControllerHtaccessAudit;
use Illuminate\Console\Command;

/**
 * Cantiere 16 (programma 100-cantieri Kairus, dipende dal Cantiere 15 —
 * vedi docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md). Gate di rilascio di
 * sola lettura: verifica che `public/.htaccess` di questa release
 * contenga ancora le direttive critiche documentate nel runbook, prima
 * che un operatore lo copi in `~/public_html/.htaccess` seguendo la
 * stessa procedura.
 */
class DeployVerifyFrontController extends Command
{
    protected $signature = 'deploy:verify-front-controller';

    protected $description = 'Verifica che public/.htaccess contenga le direttive critiche documentate nel runbook cPanel. Solo lettura, non modifica mai alcun file.';

    public function handle(FrontControllerHtaccessAudit $audit): int
    {
        $report = $audit->report();

        if (! $report['exists']) {
            $this->error("public/.htaccess non trovato in questa release ({$report['path']}) — vedi docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md.");

            return self::FAILURE;
        }

        if ($report['ok']) {
            $this->info('public/.htaccess contiene tutte le direttive critiche attese.');

            return self::SUCCESS;
        }

        $this->error('public/.htaccess manca di una o più direttive critiche documentate in docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md — vedi elenco sotto. Questo NON verifica la copia realmente servita da ~/public_html/.htaccess (fuori dalla portata di questo repository), solo il file git-tracked di questa release.');

        foreach ($report['missing'] as $label) {
            $this->line("  - {$label}");
        }

        return self::FAILURE;
    }
}
