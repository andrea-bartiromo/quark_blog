<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Missione 10 (secondo batch autonomo KAIRUS, Fase B — Deployment
 * Reliability). Vedi App\Console\Commands\DeploySyncPlan per il contesto:
 * un piano di sincronizzazione a sola lettura sul gap documentato in
 * docs/DEPLOYMENT.md (nessuno strumento in questo repository copia
 * davvero i file di release sulla radice servita).
 */
class DeploySyncPlanTest extends TestCase
{
    private const MARKER = 'kairus-test-syncplan-served-root-';

    private ?string $servedRoot = null;

    /** @var string[] */
    private array $appFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->appFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        if ($this->servedRoot !== null && str_contains($this->servedRoot, self::MARKER)) {
            $this->deleteRecursively($this->servedRoot);
        }

        parent::tearDown();
    }

    private function makeServedRoot(): string
    {
        $this->servedRoot = sys_get_temp_dir().'/'.self::MARKER.uniqid('', true);
        mkdir($this->servedRoot, 0775, true);
        config(['deploy.served_public_root' => $this->servedRoot]);

        return $this->servedRoot;
    }

    private function writeAppFile(string $relative, string $contents): void
    {
        $path = public_path($relative);
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
        $this->appFiles[] = $path;
    }

    private function writeServedFile(string $relative, string $contents): void
    {
        $path = $this->servedRoot.'/'.$relative;
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
    }

    private function deleteRecursively(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteRecursively($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    public function test_the_command_succeeds_and_explains_itself_when_no_served_root_is_configured(): void
    {
        config(['deploy.served_public_root' => null]);

        Artisan::call('deploy:sync-plan');

        $this->assertSame(0, Artisan::call('deploy:sync-plan'));
        $this->assertStringContainsString('non è configurato', Artisan::output());
    }

    public function test_the_plan_classifies_a_mixed_scenario_into_the_four_expected_buckets(): void
    {
        $servedRoot = $this->makeServedRoot();
        $release = 'sync-plan-sim-'.uniqid('', true);

        // Da aggiungere: solo sull'albero applicativo.
        $this->writeAppFile("$release/css/new.css", '.new{color:red}');

        // Da aggiornare: contenuto diverso sui due lati.
        $this->writeAppFile("$release/css/changed.css", 'body{color:blue} /* v2 */');
        $this->writeServedFile("$release/css/changed.css", 'body{color:blue} /* v1 */');

        // Invariato.
        $this->writeAppFile("$release/js/app.js", 'console.log(1)');
        $this->writeServedFile("$release/js/app.js", 'console.log(1)');

        // Solo sulla radice servita: mai toccato da questo strumento.
        $this->writeServedFile("$release/css/orphan.css", '.orphan{}');

        config(['deploy.asset_drift_scan_paths' => [$release]]);

        $exitCode = Artisan::call('deploy:sync-plan');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Da AGGIUNGERE sulla radice servita (1):', $output);
        $this->assertStringContainsString("$release/css/new.css", $output);
        $this->assertStringContainsString('Da AGGIORNARE sulla radice servita (1):', $output);
        $this->assertStringContainsString("$release/css/changed.css", $output);
        $this->assertStringContainsString('Invariati, nessuna azione (1):', $output);
        $this->assertStringContainsString("$release/js/app.js", $output);
        $this->assertStringContainsString('IGNORATI intenzionalmente (1)', $output);
        $this->assertStringContainsString("$release/css/orphan.css", $output);
    }

    public function test_the_command_never_writes_to_either_root(): void
    {
        $servedRoot = $this->makeServedRoot();
        $release = 'sync-plan-immutable-'.uniqid('', true);

        $this->writeAppFile("$release/css/only-app.css", 'a{}');
        config(['deploy.asset_drift_scan_paths' => [$release]]);

        Artisan::call('deploy:sync-plan');

        $this->assertFalse(is_file($servedRoot.'/'.$release.'/css/only-app.css'), 'deploy:sync-plan must never write to the served root — it is dry-run only.');
    }
}
