<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Cantiere 18 (programma 100-cantieri Kairus): deploy:verify-persistent-storage
 * è solo informativo (mai un vero gate di per sé — deploy.sh lo invoca
 * senza `|| fail`), ma il comando stesso deve comunque distinguere lo
 * stato pulito da quello a rischio tramite l'exit code, per chi volesse
 * usarlo come gate in un contesto diverso.
 */
class DeployVerifyPersistentStorageCommandTest extends TestCase
{
    public function test_passes_when_checked_paths_are_outside_this_release(): void
    {
        config([
            'backup.v2.directory' => '/var/kairus-shared/backups/mariadb',
            'deploy.release_registry_path' => null,
        ]);

        $this->artisan('deploy:verify-persistent-storage')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun percorso a rischio');
    }

    public function test_fails_when_the_backup_directory_resolves_inside_this_release(): void
    {
        config(['backup.v2.directory' => storage_path('backups/mariadb')]);

        $this->artisan('deploy:verify-persistent-storage')
            ->assertExitCode(1)
            ->expectsOutputToContain('DB_BACKUP_DIRECTORY');
    }
}
