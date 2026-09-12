<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\PersistentStoragePreflight;
use Tests\TestCase;

/**
 * Cantiere 18 (programma 100-cantieri Kairus).
 * App\Services\Deploy\PersistentStoragePreflight segnala i percorsi che
 * devono sopravvivere tra un rilascio e l'altro (backup MariaDB, registro
 * rilasci) ma sono configurati dentro QUESTA directory di release — con
 * lo schema a directory separate + switch di symlink già in uso in
 * produzione, verrebbero perduti al deploy successivo.
 */
class PersistentStoragePreflightTest extends TestCase
{
    public function test_reports_ok_when_checked_paths_are_configured_outside_this_release(): void
    {
        config([
            'backup.v2.directory' => '/var/kairus-shared/backups/mariadb',
            'deploy.release_registry_path' => '/var/kairus-shared/release-registry.jsonl',
        ]);

        $report = app(PersistentStoragePreflight::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['at_risk']);
        $this->assertSame(rtrim(base_path(), '/'), $report['release_root']);
    }

    public function test_flags_the_backup_directory_when_it_resolves_inside_this_release(): void
    {
        config(['backup.v2.directory' => storage_path('backups/mariadb')]);

        $report = app(PersistentStoragePreflight::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertCount(1, $report['at_risk']);
        $this->assertSame('DB_BACKUP_DIRECTORY', $report['at_risk'][0]['env_var']);
    }

    public function test_flags_the_release_registry_when_it_resolves_inside_this_release(): void
    {
        config([
            'backup.v2.directory' => '/var/kairus-shared/backups/mariadb',
            'deploy.release_registry_path' => base_path('release-registry.jsonl'),
        ]);

        $report = app(PersistentStoragePreflight::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertCount(1, $report['at_risk']);
        $this->assertSame('DEPLOY_RELEASE_REGISTRY_PATH', $report['at_risk'][0]['env_var']);
    }

    public function test_flags_every_at_risk_path_independently(): void
    {
        config([
            'backup.v2.directory' => storage_path('backups/mariadb'),
            'deploy.release_registry_path' => base_path('release-registry.jsonl'),
        ]);

        $report = app(PersistentStoragePreflight::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertCount(2, $report['at_risk']);
        $this->assertSame(
            ['DB_BACKUP_DIRECTORY', 'DEPLOY_RELEASE_REGISTRY_PATH'],
            array_column($report['at_risk'], 'env_var')
        );
    }

    /**
     * Il registro rilasci è disattivato di default (nessun valore
     * configurato): questo è lo stato deliberato e documentato in
     * docs/DEPLOYMENT.md, non un rischio da segnalare qui.
     */
    public function test_an_unconfigured_release_registry_path_is_not_a_risk(): void
    {
        config([
            'backup.v2.directory' => '/var/kairus-shared/backups/mariadb',
            'deploy.release_registry_path' => null,
        ]);

        $report = app(PersistentStoragePreflight::class)->report();

        $this->assertTrue($report['ok']);
    }

    /**
     * Stesso principio già applicato da CachedConfigPathAudit: una
     * directory SORELLA che condivide solo il prefisso testuale (es. lo
     * stesso percorso di release con un suffisso "-old") non deve essere
     * scambiata per "dentro" questa directory di release.
     */
    public function test_a_sibling_directory_that_only_shares_the_textual_prefix_is_not_flagged(): void
    {
        config(['backup.v2.directory' => rtrim(base_path(), '/').'-old/backups']);

        $report = app(PersistentStoragePreflight::class)->report();

        $this->assertTrue($report['ok']);
    }
}
