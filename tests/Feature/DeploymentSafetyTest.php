<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentSafetyTest extends TestCase
{
    public function test_production_deploy_requires_and_records_expected_revision(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('EXPECTED_SHA="${1:-}"', $script);
        $this->assertStringContainsString('[0-9a-fA-F]{40}', $script);
        $this->assertStringContainsString('REVISION', $script);
        $this->assertStringContainsString('DEPLOY_INFO', $script);
        $this->assertStringContainsString('date -u', $script);
    }

    public function test_production_deploy_requires_tracked_release_files_to_match_expected_revision(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('git -c core.fileMode=false diff --quiet --ignore-submodules --', $script);
        $this->assertStringContainsString('git -c core.fileMode=false diff --cached --quiet --ignore-submodules --', $script);
        $this->assertStringContainsString('Tracked release files differ from the expected Git revision', $script);
    }

    /**
     * A real bug, found only by actually running deploy.sh twice against
     * the same checkout (see .github/workflows/deploy-safety.yml's own
     * "second deploy.sh run" test): the script's own `chmod -R 755 storage
     * bootstrap/cache` flips tracked files inside bootstrap/cache from the
     * repo's 644 to 755 with zero content change, which then made a SECOND
     * run's dirty-release-artifact check spuriously fail on mode bits
     * alone. `core.fileMode=false` still fails closed on any real content
     * drift — the actual safety intent — it only stops mode-only noise
     * from the script's own prior side effect.
     */
    public function test_production_deploy_tracked_files_check_ignores_its_own_chmod_side_effect(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('chmod -R 755 storage bootstrap/cache', $script);

        preg_match_all('/git -c core\.fileMode=false diff( --cached)? --quiet --ignore-submodules --/', $script, $matches);
        $this->assertCount(2, $matches[0], 'Expected exactly the two tracked-files dirty checks to use core.fileMode=false.');
    }

    public function test_production_deploy_does_not_assume_sqlite_or_run_the_sqlite_only_backup_command(): void
    {
        $script = $this->deployScript();

        $this->assertStringNotContainsString('database/database.sqlite', $script);
        $this->assertStringNotContainsString('php artisan backup:database', $script);
        $this->assertStringContainsString('mysql', $script);
        $this->assertStringContainsString('mariadb', $script);
    }

    public function test_production_deploy_fails_closed_when_migrations_are_pending_without_backup_v2(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString('php artisan migrate:status --no-ansi', $script);
        $this->assertStringContainsString('Pending', $script);
        $this->assertStringNotContainsString('php artisan migrate --force', $script);
    }

    public function test_production_deploy_never_generates_a_new_app_key(): void
    {
        $script = $this->deployScript();

        $this->assertStringNotContainsString('php artisan key:generate --force', $script);
        $this->assertStringContainsString('APP_KEY', $script);
    }

    public function test_production_environment_example_targets_mysql_mariadb_configuration(): void
    {
        $env = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($env);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);
        $this->assertStringContainsString('DB_HOST=', $env);
        $this->assertStringContainsString('DB_PORT=', $env);
        $this->assertStringContainsString('DB_DATABASE=', $env);
        $this->assertStringContainsString('DB_USERNAME=', $env);
        $this->assertStringContainsString('DB_PASSWORD=', $env);
        $this->assertStringNotContainsString('DB_CONNECTION=sqlite', $env);
    }

    public function test_sqlite_remains_the_deterministic_test_database(): void
    {
        $phpunit = file_get_contents(base_path('phpunit.xml'));
        $workflow = file_get_contents(base_path('.github/workflows/tests.yml'));

        $this->assertIsString($phpunit);
        $this->assertIsString($workflow);
        $this->assertStringContainsString('DB_CONNECTION" value="sqlite"', $phpunit);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $workflow);
    }

    public function test_scheduled_sqlite_backup_is_guarded_outside_sqlite_environments(): void
    {
        $schedule = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($schedule);
        $this->assertStringContainsString("config('database.default') === 'sqlite'", $schedule);
        $this->assertStringContainsString("Schedule::command('backup:database')", $schedule);
    }

    /**
     * Missione 19 (secondo batch autonomo KAIRUS, Fase B — Deployment
     * Reliability — "Release metadata integrity"): REVISION and DEPLOY_INFO
     * are two separate files written from the same shell variable
     * ($ACTUAL_SHA). Nothing before this test asserted they stay derived
     * from that single variable, so a future edit could desync them (e.g.
     * DEPLOY_INFO's revision= line quietly reintroducing $EXPECTED_SHA, the
     * pre-verification input, instead of the verified $ACTUAL_SHA) with no
     * regression to catch it. This locks both writes to the same source of
     * truth and the write order — always last, only after every check
     * above (migration gate, asset drift gate) has already passed.
     *
     * Missione 13 (già in questo batch, PR precedente) ha già coperto la
     * cattura dell'hash per-file nei manifest di selective-deploy-backup;
     * questa missione copre un gap diverso e non ancora testato — la
     * coerenza reciproca tra REVISION e DEPLOY_INFO dentro deploy.sh
     * stesso, non il meccanismo di backup/rollback.
     */
    public function test_production_deploy_records_revision_and_deploy_info_from_the_same_verified_sha(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString("printf '%s\\n' \"\$ACTUAL_SHA\" > REVISION", $script);
        $this->assertStringContainsString("printf 'revision=%s\\n' \"\$ACTUAL_SHA\"", $script);
        $this->assertStringNotContainsString('$EXPECTED_SHA" > REVISION', $script);
        $this->assertStringNotContainsString("revision=%s\\n' \"\$EXPECTED_SHA\"", $script);

        $revisionWritePosition = strpos($script, '> REVISION');
        $assetDriftCheckPosition = strpos($script, 'deploy:asset-drift');
        $migrationGatePosition = strpos($script, 'Pending migrations detected');

        $this->assertNotFalse($revisionWritePosition);
        $this->assertNotFalse($assetDriftCheckPosition);
        $this->assertNotFalse($migrationGatePosition);
        $this->assertGreaterThan($migrationGatePosition, $revisionWritePosition, 'REVISION/DEPLOY_INFO must be recorded after the migration safety gate, never before it.');
        $this->assertGreaterThan($assetDriftCheckPosition, $revisionWritePosition, 'REVISION/DEPLOY_INFO must be recorded after the asset drift gate, never before it.');
    }

    /**
     * DEPLOY_INFO è un artefatto write-only per l'operatore: nessun codice
     * applicativo lo legge (verificato con grep su app/ durante l'indagine
     * di questa missione). Il suo unico requisito di integrità è
     * strutturale — i tre campi su cui un operatore fa affidamento
     * ispezionando una release devono essere sempre presenti e in questa
     * forma.
     */
    public function test_deploy_info_records_the_three_fields_an_operator_relies_on(): void
    {
        $script = $this->deployScript();

        $this->assertStringContainsString("printf 'revision=%s\\n' \"\$ACTUAL_SHA\"", $script);
        $this->assertStringContainsString("printf 'deployed_at_utc=%s\\n' \"\$(date -u '+%Y-%m-%dT%H:%M:%SZ')\"", $script);
        $this->assertStringContainsString("printf 'database_driver=%s\\n' \"\$DB_CONNECTION_VALUE\"", $script);
    }

    private function deployScript(): string
    {
        $script = file_get_contents(base_path('deploy.sh'));

        $this->assertIsString($script);

        return $script;
    }
}
