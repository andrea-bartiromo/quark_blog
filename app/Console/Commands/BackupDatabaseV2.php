<?php

namespace App\Console\Commands;

use App\Services\Backup\MariaDbBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabaseV2 extends Command
{
    protected $signature = 'backup:database-v2 {--mode=periodic : periodic or pre-migration} {--release-sha= : Required for pre-migration mode}';

    protected $description = 'Create a validated, atomically published MariaDB/MySQL logical backup';

    public function handle(MariaDbBackupService $backup): int
    {
        try {
            $result = $backup->create((string) $this->option('mode'), $this->option('release-sha'));
            $this->info('Backup V2 published: '.basename($result['artifact']));
            $this->line('SHA-256: '.$result['sha256']);
            $this->line('Size: '.$result['size_bytes'].' bytes');

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Service exceptions are intentionally sanitized and never contain credentials/process argv.
            $this->error('Backup V2 failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
