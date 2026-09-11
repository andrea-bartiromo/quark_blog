<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Prompt 7 (programma 100-prompt Kairus): release:record-registry è
 * pensato per essere richiamato da deploy.sh come ultimo passo, dopo
 * ogni gate — non deve mai poter fallire chiuso e bloccare un rilascio
 * altrimenti già verificato. Vedi App\Services\Deploy\ReleaseRegistry.
 */
class ReleaseRegistryRecordCommandTest extends TestCase
{
    private const MARKER = 'kairus-test-release-registry-cmd-';

    private ?string $registryPath = null;

    protected function tearDown(): void
    {
        if ($this->registryPath !== null && str_contains($this->registryPath, self::MARKER)) {
            @unlink($this->registryPath);
        }

        parent::tearDown();
    }

    public function test_no_op_success_when_registry_is_not_configured(): void
    {
        config(['deploy.release_registry_path' => null]);

        $this->artisan('release:record-registry', ['revision' => 'deadbeef'])
            ->assertExitCode(0)
            ->expectsOutputToContain('non è configurato');
    }

    public function test_records_an_entry_when_configured(): void
    {
        $this->registryPath = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true).'.jsonl';
        config(['deploy.release_registry_path' => $this->registryPath]);

        $this->artisan('release:record-registry', ['revision' => 'deadbeef', '--stage' => 'deployed'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Registrato nel release registry');

        $this->assertFileExists($this->registryPath);
        $decoded = json_decode(trim(file_get_contents($this->registryPath)), true);
        $this->assertSame('deadbeef', $decoded['revision']);
        $this->assertSame('deployed', $decoded['stage']);
    }

    /**
     * Il cuore del contratto "mai bloccante": anche quando il registro è
     * configurato ma la scrittura fallisce davvero (qui: una directory
     * inesistente), il comando esce comunque con successo — solo un
     * avviso, mai un fallimento che potrebbe far fallire deploy.sh
     * nonostante il `|| true` (difesa in profondità, non solo una
     * dipendenza dalla shell).
     */
    public function test_exits_successfully_even_when_the_write_genuinely_fails(): void
    {
        config(['deploy.release_registry_path' => '/this/directory/does/not/exist/registry.jsonl']);

        $this->artisan('release:record-registry', ['revision' => 'deadbeef'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Impossibile scrivere sul release registry');
    }
}
