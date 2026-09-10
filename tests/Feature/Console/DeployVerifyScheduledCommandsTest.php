<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `deploy:verify-scheduled-commands` is the exit-code contract deploy.sh
 * relies on (mirrors deploy:asset-drift's role for the public asset
 * drift gate): 0 when every routes/console.php Schedule::command(...)
 * name is registered by Artisan in THIS process, non-zero otherwise.
 *
 * These are in-process tests of the command's own parsing/comparison
 * logic. They deliberately do NOT prove the command catches the real
 * production incident (newsletter:reconfirmation-cleanup silently
 * missing from a real release's Artisan runtime) — an in-process test
 * cannot, by construction, since the incident only manifested in a
 * separate, real `php artisan` subprocess. That proof lives in
 * DeploymentSafetyTest::test_production_deploy_verify_scheduled_commands_gate_fails_closed_on_a_real_broken_release_worktree,
 * which runs this exact command as a real subprocess against a real git
 * worktree with a deliberately broken command.
 */
class DeployVerifyScheduledCommandsTest extends TestCase
{
    public function test_it_succeeds_when_every_scheduled_command_is_registered(): void
    {
        $exitCode = Artisan::call('deploy:verify-scheduled-commands');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('scheduled commands are registered', Artisan::output());
    }

    public function test_it_fails_when_a_scheduled_command_is_not_registered(): void
    {
        $consoleRoutesPath = base_path('routes/console.php');
        $original = file_get_contents($consoleRoutesPath);
        $this->assertIsString($original);

        try {
            file_put_contents(
                $consoleRoutesPath,
                $original."\n\\Illuminate\\Support\\Facades\\Schedule::command('this-command-does-not-exist')->daily();\n"
            );

            $exitCode = Artisan::call('deploy:verify-scheduled-commands');
            // Artisan::output() wraps a Symfony BufferedOutput::fetch(),
            // which empties the buffer on read — capture it once.
            $output = Artisan::output();

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('this-command-does-not-exist', $output);
            $this->assertStringContainsString('newsletter:reconfirmation-cleanup incident class', $output);
        } finally {
            file_put_contents($consoleRoutesPath, $original);
        }
    }

    public function test_it_fails_closed_when_routes_console_has_no_scheduled_commands(): void
    {
        $consoleRoutesPath = base_path('routes/console.php');
        $original = file_get_contents($consoleRoutesPath);
        $this->assertIsString($original);

        try {
            file_put_contents($consoleRoutesPath, "<?php\n// no scheduled commands in this fixture\n");

            $exitCode = Artisan::call('deploy:verify-scheduled-commands');

            $this->assertSame(1, $exitCode);
            $this->assertStringContainsString('no Schedule::command', Artisan::output());
        } finally {
            file_put_contents($consoleRoutesPath, $original);
        }
    }
}
