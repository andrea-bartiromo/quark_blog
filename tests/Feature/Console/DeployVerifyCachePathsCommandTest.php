<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Prompt 8 (programma 100-prompt Kairus): deploy:verify-cache-paths è un
 * vero gate di rilascio — deve fallire chiuso (exit code diverso da
 * zero) quando la config cache non riflette la directory corrente.
 */
class DeployVerifyCachePathsCommandTest extends TestCase
{
    public function test_passes_when_cached_paths_match_this_environment(): void
    {
        $this->artisan('deploy:verify-cache-paths')
            ->assertExitCode(0)
            ->expectsOutputToContain('puntano correttamente');
    }

    public function test_fails_closed_when_a_cached_path_points_outside_this_release(): void
    {
        config(['session.files' => '/a/different/release/storage/framework/sessions']);

        $this->artisan('deploy:verify-cache-paths')
            ->assertExitCode(1)
            ->expectsOutputToContain('directory di release diversa');
    }
}
