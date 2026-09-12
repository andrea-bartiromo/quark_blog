<?php

namespace App\Console\Commands;

use App\Services\NewsletterReconfirmationService;
use Illuminate\Console\Command;

class ProcessNewsletterReconfirmation extends Command
{
    protected $signature = 'newsletter:reconfirmation-process {--dry-run : Mostra le azioni dovute senza inviare o modificare dati}';

    protected $description = 'Invia i solleciti newsletter dovuti e rimuove solo i pending dopo il terzo sollecito';

    public function handle(NewsletterReconfirmationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! config('newsletter.reconfirmation.automation_enabled')) {
            $this->warn('Automazione riconferma disattivata. Nessun invio o rimozione.');

            return self::SUCCESS;
        }

        $result = $service->process($dryRun);

        $this->info(sprintf(
            '%s: %d solleciti, %d rimozioni, %d invariati, %d anomalie.',
            $dryRun ? 'Dry-run' : 'Processo completato',
            $result['reminders'],
            $result['deletions'],
            $result['skipped'],
            $result['failures'],
        ));

        return self::SUCCESS;
    }
}
