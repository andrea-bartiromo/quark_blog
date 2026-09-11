<?php

namespace Tests\Feature\Console;

use App\Services\Deploy\ReleaseRegistry;
use Tests\TestCase;

/**
 * Prompt 7 (programma 100-prompt Kairus): release:registry è puramente
 * diagnostico, read-only — mai muta il registro.
 */
class ReleaseRegistryShowCommandTest extends TestCase
{
    private const MARKER = 'kairus-test-release-registry-show-';

    private ?string $registryPath = null;

    protected function tearDown(): void
    {
        if ($this->registryPath !== null && str_contains($this->registryPath, self::MARKER)) {
            @unlink($this->registryPath);
        }

        parent::tearDown();
    }

    public function test_reports_disabled_when_not_configured(): void
    {
        config(['deploy.release_registry_path' => null]);

        $this->artisan('release:registry')
            ->assertExitCode(0)
            ->expectsOutputToContain('non è configurato');
    }

    public function test_reports_empty_history_when_configured_but_no_entries_exist_yet(): void
    {
        $this->registryPath = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true).'.jsonl';
        config(['deploy.release_registry_path' => $this->registryPath]);

        $this->artisan('release:registry')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessuna revisione registrata');
    }

    public function test_shows_recorded_history_in_a_table(): void
    {
        $this->registryPath = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true).'.jsonl';
        config(['deploy.release_registry_path' => $this->registryPath]);

        $registry = app(ReleaseRegistry::class);
        $registry->record('0123456789abcdef', ReleaseRegistry::STAGE_DEPLOYED);
        $registry->record('0123456789abcdef', ReleaseRegistry::STAGE_VERIFIED);

        $this->artisan('release:registry')
            ->assertExitCode(0)
            ->expectsOutputToContain('0123456789ab');
    }

    public function test_json_output_is_valid_and_reflects_the_registry_state(): void
    {
        $this->registryPath = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true).'.jsonl';
        config(['deploy.release_registry_path' => $this->registryPath]);

        app(ReleaseRegistry::class)->record('deadbeef', ReleaseRegistry::STAGE_DEPLOYED);

        $this->artisan('release:registry', ['--json' => true])->assertExitCode(0);

        $registry = app(ReleaseRegistry::class);
        $this->assertTrue($registry->isEnabled());
        $this->assertSame('deadbeef', $registry->history()[0]['revision']);
    }
}
