<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Cantiere 16 (programma 100-cantieri Kairus): deploy:verify-front-controller
 * è un gate di rilascio — deve fallire chiuso (exit code diverso da zero)
 * quando public/.htaccess manca di una direttiva critica documentata in
 * docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md.
 */
class DeployVerifyFrontControllerCommandTest extends TestCase
{
    public function test_passes_when_public_htaccess_has_every_critical_directive(): void
    {
        $this->artisan('deploy:verify-front-controller')
            ->assertExitCode(0)
            ->expectsOutputToContain('direttive critiche attese');
    }
}
