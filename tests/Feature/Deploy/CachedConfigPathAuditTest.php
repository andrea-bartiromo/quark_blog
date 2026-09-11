<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\CachedConfigPathAudit;
use Tests\TestCase;

/**
 * Prompt 8 (programma 100-prompt Kairus): App\Services\Deploy\CachedConfigPathAudit
 * verifica che i valori di config calcolati da storage_path() puntino
 * ancora sotto la directory corrente — il sintomo di un
 * bootstrap/cache/config.php sopravvissuto da una release con un
 * percorso diverso. Vedi anche
 * tests/Feature/DeployVerifyCachePathsRealSubprocessTest.php per la
 * prova end-to-end con due directory di release realmente diverse.
 */
class CachedConfigPathAuditTest extends TestCase
{
    public function test_reports_ok_when_every_checked_path_is_under_the_real_storage_path(): void
    {
        $report = app(CachedConfigPathAudit::class)->report();

        $this->assertTrue($report['ok']);
        $this->assertSame([], $report['problems']);
        $this->assertSame(rtrim(storage_path(), '/'), $report['expected_prefix']);
    }

    public function test_flags_a_config_value_that_points_outside_the_real_storage_path(): void
    {
        config(['logging.channels.daily.path' => '/some/other/release/storage/logs/laravel.log']);

        $report = app(CachedConfigPathAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertCount(1, $report['problems']);
        $this->assertSame('logging.channels.daily.path', $report['problems'][0]['key']);
        $this->assertSame('/some/other/release/storage/logs/laravel.log', $report['problems'][0]['value']);
    }

    public function test_flags_every_stale_key_independently(): void
    {
        config([
            'logging.channels.daily.path' => '/stale/storage/logs/laravel.log',
            'session.files' => '/stale/storage/framework/sessions',
        ]);

        $report = app(CachedConfigPathAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertCount(2, $report['problems']);
        $this->assertSame(
            ['logging.channels.daily.path', 'session.files'],
            array_column($report['problems'], 'key')
        );
    }

    public function test_a_key_with_no_value_configured_in_this_environment_is_not_a_problem(): void
    {
        config(['logging.channels.newsletter_reconfirmation_audit.path' => null]);

        $report = app(CachedConfigPathAudit::class)->report();

        $this->assertTrue($report['ok']);
    }

    /**
     * Finding Codex su #547 (dopo il merge): str_starts_with() da solo
     * accetta anche una directory SORELLA che condivide solo il
     * prefisso testuale (qui "{storage_path}-old") come se fosse sotto
     * questa release — il falso negativo esatto che questo gate
     * fail-closed deve escludere, perché un percorso del genere non è
     * affatto sotto storage_path() reale.
     */
    public function test_flags_a_sibling_directory_that_only_shares_the_textual_prefix(): void
    {
        config(['logging.channels.daily.path' => storage_path().'-old/logs/laravel.log']);

        $report = app(CachedConfigPathAudit::class)->report();

        $this->assertFalse($report['ok']);
        $this->assertCount(1, $report['problems']);
        $this->assertSame('logging.channels.daily.path', $report['problems'][0]['key']);
    }

    public function test_the_expected_prefix_itself_with_no_trailing_segment_is_not_a_problem(): void
    {
        config(['session.files' => storage_path()]);

        $report = app(CachedConfigPathAudit::class)->report();

        $this->assertTrue($report['ok']);
    }
}
