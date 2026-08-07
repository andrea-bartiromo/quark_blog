<?php

namespace App\Console\Commands;

use App\Services\Communication\SubscriberMigrationService;
use Illuminate\Console\Command;

class MigrateCommunicationSubscribers extends Command
{
    protected $signature = 'communication:migrate-subscribers {--dry-run : Mostra cosa verrebbe copiato senza scrivere nulla}';

    protected $description = 'Copia gli iscritti dalla tabella newsletter esistente a comm_subscribers, senza modificare né eliminare la sorgente';

    public function handle(SubscriberMigrationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = $service->migrate($dryRun);

        $label = $dryRun ? 'Simulazione (--dry-run, nessuna scrittura)' : 'Migrazione';
        $this->info("{$label} completata.");
        $this->line("Copiati: {$result['copied']}");
        $this->line("Già presenti (saltati): {$result['already_present']}");
        $this->line('Errori: '.count($result['errors']));

        if ($result['errors'] !== []) {
            $this->warn('Righe non copiate:');
            foreach ($result['errors'] as $error) {
                $this->line("  - {$error['email']}: {$error['message']}");
            }
        }

        return Command::SUCCESS;
    }
}
