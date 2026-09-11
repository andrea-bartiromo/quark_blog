<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\ReleaseRegistry;
use RuntimeException;
use Tests\TestCase;

/**
 * Prompt 7 (programma 100-prompt Kairus): App\Services\Deploy\ReleaseRegistry
 * è un log append-only opzionale (DEPLOY_RELEASE_REGISTRY_PATH) che
 * sopravvive a REVISION/DEPLOY_INFO, scartati ad ogni nuovo rilascio —
 * vedi docs/DEPLOYMENT.md, sezione "Release registry".
 */
class ReleaseRegistryTest extends TestCase
{
    private const MARKER = 'kairus-test-release-registry-';

    private ?string $registryPath = null;

    protected function tearDown(): void
    {
        if ($this->registryPath !== null && str_contains($this->registryPath, self::MARKER)) {
            @unlink($this->registryPath);
        }

        parent::tearDown();
    }

    private function registry(): ReleaseRegistry
    {
        return app(ReleaseRegistry::class);
    }

    private function useTempRegistryPath(): string
    {
        $this->registryPath = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true).'.jsonl';
        config(['deploy.release_registry_path' => $this->registryPath]);

        return $this->registryPath;
    }

    public function test_disabled_by_default_when_no_path_is_configured(): void
    {
        config(['deploy.release_registry_path' => null]);

        $registry = $this->registry();

        $this->assertFalse($registry->isEnabled());
        $this->assertFalse($registry->record('abc123', ReleaseRegistry::STAGE_DEPLOYED));
        $this->assertSame([], $registry->entries());
        $this->assertSame([], $registry->history());
    }

    public function test_records_and_reads_back_a_single_entry(): void
    {
        $this->useTempRegistryPath();
        $registry = $this->registry();

        $this->assertTrue($registry->isEnabled());
        $this->assertTrue($registry->record('deadbeef', ReleaseRegistry::STAGE_DEPLOYED, 'first release'));

        $entries = $registry->entries();

        $this->assertCount(1, $entries);
        $this->assertSame('deadbeef', $entries[0]['revision']);
        $this->assertSame(ReleaseRegistry::STAGE_DEPLOYED, $entries[0]['stage']);
        $this->assertSame('first release', $entries[0]['note']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $entries[0]['recorded_at_utc']);
    }

    public function test_appends_without_overwriting_previous_entries(): void
    {
        $this->useTempRegistryPath();
        $registry = $this->registry();

        $registry->record('sha-one', ReleaseRegistry::STAGE_DEPLOYED);
        $registry->record('sha-two', ReleaseRegistry::STAGE_DEPLOYED);
        $registry->record('sha-one', ReleaseRegistry::STAGE_VERIFIED);

        $entries = $registry->entries();

        $this->assertCount(3, $entries);
        $this->assertSame(['sha-one', 'sha-two', 'sha-one'], array_column($entries, 'revision'));
    }

    public function test_history_groups_by_revision_keeping_first_seen_timestamp_per_stage(): void
    {
        $this->useTempRegistryPath();
        $registry = $this->registry();

        $registry->record('sha-one', ReleaseRegistry::STAGE_DEPLOYED);
        $registry->record('sha-two', ReleaseRegistry::STAGE_DEPLOYED);
        $registry->record('sha-one', ReleaseRegistry::STAGE_VERIFIED);
        // A second "deployed" event for sha-one (e.g. a redeploy of the
        // same revision after a rollback) must not overwrite the first
        // timestamp already recorded for that stage.
        $registry->record('sha-one', ReleaseRegistry::STAGE_DEPLOYED);

        $history = $registry->history();

        $this->assertCount(2, $history);
        $this->assertSame('sha-one', $history[0]['revision']);
        $this->assertArrayHasKey(ReleaseRegistry::STAGE_DEPLOYED, $history[0]['stages']);
        $this->assertArrayHasKey(ReleaseRegistry::STAGE_VERIFIED, $history[0]['stages']);
        $this->assertSame('sha-two', $history[1]['revision']);
        $this->assertArrayHasKey(ReleaseRegistry::STAGE_DEPLOYED, $history[1]['stages']);
        $this->assertArrayNotHasKey(ReleaseRegistry::STAGE_VERIFIED, $history[1]['stages']);
    }

    public function test_malformed_lines_are_skipped_without_breaking_the_rest_of_the_history(): void
    {
        $path = $this->useTempRegistryPath();
        $registry = $this->registry();

        $registry->record('sha-good-1', ReleaseRegistry::STAGE_DEPLOYED);
        file_put_contents($path, "not even json\n", FILE_APPEND);
        file_put_contents($path, json_encode(['revision' => 'missing-stage']).PHP_EOL, FILE_APPEND);
        $registry->record('sha-good-2', ReleaseRegistry::STAGE_DEPLOYED);

        $entries = $registry->entries();

        $this->assertSame(['sha-good-1', 'sha-good-2'], array_column($entries, 'revision'));
    }

    public function test_record_throws_when_configured_but_the_directory_does_not_exist(): void
    {
        config(['deploy.release_registry_path' => '/this/directory/does/not/exist/registry.jsonl']);

        $this->expectException(RuntimeException::class);

        $this->registry()->record('sha', ReleaseRegistry::STAGE_DEPLOYED);
    }

    /**
     * Finding Codex su #546 (dopo il merge): un percorso configurato
     * dentro la directory di release veniva accettato in silenzio da
     * record() e la storia scritta lì sarebbe andata persa al prossimo
     * switch di symlink — esattamente il problema che questo registro
     * esiste per risolvere. base_path('storage/framework/testing/...')
     * è dentro la release corrente per costruzione.
     */
    public function test_record_throws_when_the_configured_path_resolves_inside_the_release_directory(): void
    {
        config(['deploy.release_registry_path' => base_path('storage/framework/testing/inside-release-registry.jsonl')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must resolve outside the release directory/');

        $this->registry()->record('sha', ReleaseRegistry::STAGE_DEPLOYED);
    }

    /**
     * Un percorso relativo risolve rispetto alla working directory del
     * processo — durante un deploy reale quella directory È la release
     * corrente, quindi un percorso relativo deve essere rifiutato con lo
     * stesso errore di un percorso assoluto dentro base_path(), senza
     * bisogno di un controllo "deve essere assoluto" separato.
     */
    public function test_record_throws_when_the_configured_path_is_relative(): void
    {
        config(['deploy.release_registry_path' => 'storage/framework/testing/relative-release-registry.jsonl']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/must resolve outside the release directory/');

        $this->registry()->record('sha', ReleaseRegistry::STAGE_DEPLOYED);
    }

    /**
     * Finding Codex su #546 (dopo il merge): file_get_contents() senza
     * l'operatore di soppressione errori emette un E_WARNING se il file
     * diventa illeggibile tra il check is_file() e la lettura — Laravel
     * lo converte in ErrorException, mai raggiungendo il fallback
     * $contents === false. entries() è best-effort per contratto (vedi
     * il docblock della classe) e non deve mai lanciare per un singolo
     * file illeggibile.
     */
    public function test_entries_does_not_throw_when_the_registry_file_becomes_unreadable(): void
    {
        $path = $this->useTempRegistryPath();
        $registry = $this->registry();
        $registry->record('sha-unreadable', ReleaseRegistry::STAGE_DEPLOYED);

        chmod($path, 0000);

        if (is_readable($path)) {
            chmod($path, 0644);
            $this->markTestSkipped('This process can read files regardless of permission bits (likely running as root); cannot reproduce an unreadable-file race here.');
        }

        $this->assertSame([], $registry->entries());

        chmod($path, 0644);
    }
}
