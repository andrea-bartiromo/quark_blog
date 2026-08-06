<?php

namespace App\Console\Commands;

use App\Services\ProjectTaskSyncService;
use Illuminate\Console\Command;

class SyncProjectDerivedStatuses extends Command
{
    protected $signature = 'projects:sync-derived-statuses';

    protected $description = 'Riallinea lo stato derivato dei task di Pubblicazione allo stato editoriale corrente dei rispettivi articoli';

    public function handle(ProjectTaskSyncService $service): int
    {
        $result = $service->syncAll();

        $this->info("Task aggiornati: {$result['updated']} — invariati: {$result['skipped']}.");

        return Command::SUCCESS;
    }
}
