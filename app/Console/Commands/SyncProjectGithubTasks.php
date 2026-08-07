<?php

namespace App\Console\Commands;

use App\Services\ProjectTaskGithubSyncService;
use Illuminate\Console\Command;

class SyncProjectGithubTasks extends Command
{
    protected $signature = 'projects:sync-github-tasks';

    protected $description = 'Riallinea i task di Sviluppo allo stato corrente di branch/PR su GitHub';

    public function handle(ProjectTaskGithubSyncService $service): int
    {
        $result = $service->syncAll();

        $this->info("Task aggiornati: {$result['updated']} — invariati: {$result['skipped']}.");

        return Command::SUCCESS;
    }
}
