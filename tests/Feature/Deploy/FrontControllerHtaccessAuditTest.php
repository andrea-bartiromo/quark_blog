<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\FrontControllerHtaccessAudit;
use Tests\TestCase;

/**
 * Cantiere 16 (programma 100-cantieri Kairus, dipende dal Cantiere 15 —
 * vedi docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md).
 * App\Services\Deploy\FrontControllerHtaccessAudit verifica che
 * public/.htaccess contenga ancora le direttive critiche che il runbook
 * documenta blocco per blocco — l'unica parte del livello Apache/cPanel
 * verificabile in CI, perché è l'unico file git-tracked di quel livello.
 */
class FrontControllerHtaccessAuditTest extends TestCase
{
    public function test_the_real_public_htaccess_in_this_repository_passes(): void
    {
        $report = app(FrontControllerHtaccessAudit::class)->report();

        $this->assertTrue($report['ok'], 'Direttive mancanti: '.implode(', ', $report['missing']));
        $this->assertTrue($report['exists']);
        $this->assertSame([], $report['missing']);
    }

    public function test_fails_closed_when_the_file_does_not_exist(): void
    {
        $missingPath = sys_get_temp_dir().'/kairus-htaccess-audit-test-missing-'.uniqid().'/.htaccess';

        $report = app(FrontControllerHtaccessAudit::class)->report($missingPath);

        $this->assertFalse($report['ok']);
        $this->assertFalse($report['exists']);
        $this->assertNotEmpty($report['missing']);
    }

    public function test_flags_a_missing_env_block_rule(): void
    {
        $path = $this->htaccessFixtureWithout('RewriteRule ^\.env$ - [F,L]');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(['Blocco accesso a .env'], $report['missing']);
    }

    public function test_flags_a_missing_front_controller_rewrite(): void
    {
        $path = $this->htaccessFixtureWithout('RewriteRule ^ index.php [L]');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(['Front controller (rewrite verso index.php)'], $report['missing']);
    }

    public function test_flags_every_missing_directive_independently(): void
    {
        $path = $this->htaccessFixtureWithout('X-Content-Type-Options', 'X-Frame-Options');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(
            ['Header X-Content-Type-Options', 'Header X-Frame-Options'],
            $report['missing']
        );
    }

    private function htaccessFixtureWithout(string ...$linesToRemove): string
    {
        $contents = (string) file_get_contents(public_path('.htaccess'));

        foreach ($linesToRemove as $line) {
            $contents = str_replace($line, '', $contents);
        }

        $path = sys_get_temp_dir().'/kairus-htaccess-audit-test-'.uniqid().'.htaccess';
        file_put_contents($path, $contents);
        register_shutdown_function(fn () => @unlink($path));

        return $path;
    }
}
