<?php

namespace Tests\Feature;

use App\Services\Turing\TuringInternalBetaReadinessService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Cantiere 70 (programma "100 cantieri Kairus", dipende dal Cantiere 69,
 * documentazione pura). `docs/06_Turing_Release/Piano_Rilascio_Preflight_Rollback_v1.0.md`
 * non introduce alcun comando o script nuovo: descrive solo come usare
 * meccanismi già esistenti (il flag `chapters_public`, le pagine admin
 * di sola lettura dei Cantieri 67/69, il processo di deploy già
 * documentato). Questo test verifica che ogni riferimento concreto
 * citato dal piano esista ancora davvero — stesso principio già in uso
 * in RollbackRunbookDriftTest — e che nessun comando artisan che
 * orchestri il rilascio o il rollback dello Speciale sia comparso nel
 * frattempo senza una decisione esplicita a monte.
 */
class TuringReleasePlanDriftTest extends TestCase
{
    public function test_the_plan_cites_a_config_flag_that_still_exists_with_the_documented_default(): void
    {
        $this->assertFalse(config('turing.chapters_public'));
        $this->assertArrayHasKey('chapters_public', config('turing'));
    }

    public function test_the_plan_cites_admin_routes_that_are_actually_registered(): void
    {
        $doc = $this->doc();

        foreach ([
            'admin.turing.internal-beta-readiness' => '/admin/turing/checklist-beta',
            'admin.turing.completeness-report' => '/admin/turing/report-completezza',
        ] as $routeName => $path) {
            $this->assertTrue(Route::has($routeName), "La rotta '{$routeName}' citata dal piano non risulta registrata.");
            $this->assertStringContainsString($path, $doc, "Il piano dovrebbe citare il percorso '{$path}'.");
        }
    }

    public function test_the_plan_cites_docs_that_still_exist(): void
    {
        $doc = $this->doc();

        foreach ([
            'docs/DEPLOYMENT.md',
            'docs/ROLLBACK_RUNBOOK.md',
            'docs/02_Turing_Audit/',
            'docs/06_Turing_Release/Checklist_Release_Candidate_v1.0.md',
            'docs/00_Governance/Masterplan_Speciale_Turing_v1.0.md',
            'docs/04_Turing_Visual/Registro_Asset_Turing_v1.0.md',
        ] as $referencedDoc) {
            $this->assertStringContainsString($referencedDoc, $doc);
            $this->assertFileExists(base_path(rtrim($referencedDoc, '/')));
        }
    }

    /**
     * Il piano cita per nome il tripwire del gap noto (asset Enigma
     * sottodimensionati, Cantiere 64): se quel test venisse rinominato o
     * rimosso senza aggiornare questo documento, l'affermazione
     * diventerebbe silenziosamente falsa.
     */
    public function test_the_plan_cites_the_known_asset_gap_tripwire_that_still_exists(): void
    {
        $doc = $this->doc();
        $testFile = file_get_contents(base_path('tests/Feature/TuringEditorialAssetsTest.php'));
        $this->assertIsString($testFile);

        $method = 'test_known_gap_enigma_hero_and_anatomy_fallbacks_are_undersized_for_their_cover_usage';

        $this->assertStringContainsString($method, $doc);
        $this->assertStringContainsString('function '.$method.'(', $testFile);
    }

    public function test_the_plan_cites_the_release_gate_test_that_still_covers_the_flag(): void
    {
        $doc = $this->doc();

        $this->assertStringContainsString('TuringReleaseGateTest', $doc);
        $this->assertFileExists(base_path('tests/Feature/TuringReleaseGateTest.php'));

        $this->assertStringContainsString('TuringSitemapTest', $doc);
        $this->assertFileExists(base_path('tests/Feature/TuringSitemapTest.php'));
    }

    /**
     * Finding Codex su PR #634 (P1): il piano avverte che `config:cache`
     * in deploy.sh gira PRIMA di diversi controlli fail-closed che
     * possono ancora interrompere il rilascio — se `deploy.sh` venisse
     * riordinato in futuro spostando `config:cache` DOPO quei controlli,
     * l'avvertimento diventerebbe silenziosamente obsoleto (il rischio
     * reale sparirebbe, ma il piano continuerebbe a raccomandare cautele
     * per un problema non più esistente — o peggio, un nuovo riordino
     * potrebbe introdurre un rischio diverso non coperto). Verifica che
     * l'ordine relativo citato sia ancora vero nel file reale.
     */
    public function test_the_plan_correctly_describes_config_cache_running_before_the_fail_closed_checks(): void
    {
        $script = file_get_contents(base_path('deploy.sh'));
        $this->assertIsString($script);

        $configCachePosition = strpos($script, 'php artisan config:cache');
        $this->assertNotFalse($configCachePosition, 'deploy.sh non chiama più php artisan config:cache.');

        foreach ([
            'deploy:verify-cache-paths',
            'deploy:verify-scheduled-commands',
            'deploy:verify-front-controller',
            'newsletter:reconfirmation-cleanup --dry-run',
            'deploy:asset-drift',
        ] as $laterCheck) {
            $checkPosition = strpos($script, $laterCheck);
            $this->assertNotFalse($checkPosition, "deploy.sh non contiene più il controllo '{$laterCheck}' citato dal piano.");
            $this->assertGreaterThan(
                $configCachePosition,
                $checkPosition,
                "'{$laterCheck}' non risulta più dopo 'config:cache' in deploy.sh — il piano andrebbe aggiornato di conseguenza."
            );
        }
    }

    public function test_the_internal_beta_readiness_service_cited_by_the_plan_still_exists(): void
    {
        $doc = $this->doc();

        $this->assertStringContainsString('Cantiere 69', $doc);
        $this->assertTrue(
            class_exists(TuringInternalBetaReadinessService::class),
            'App\Services\Turing\TuringInternalBetaReadinessService citato dal piano non esiste più.'
        );
    }

    /**
     * Tripwire: il piano dichiara esplicitamente che nessun comando
     * orchestra il rilascio/rollback dello Speciale end-to-end — se un
     * simile comando comparisse senza che questa affermazione venga
     * aggiornata di conseguenza, l'affermazione diventerebbe falsa in
     * silenzio, stesso principio del tripwire "no artisan
     * rollback:release" di Cantiere 75.
     */
    public function test_no_orchestrating_release_command_exists_without_updating_the_plan(): void
    {
        $doc = $this->doc();

        $this->assertStringContainsString(
            'artisan turing:release',
            $doc,
            'Il piano dovrebbe dichiarare esplicitamente che nessun comando orchestratore esiste oggi.'
        );

        foreach (array_keys(Artisan::all()) as $commandName) {
            $this->assertStringNotContainsStringIgnoringCase(
                'turing:release',
                $commandName,
                "Il comando Artisan registrato '{$commandName}' sembra orchestrare un rilascio end-to-end dello Speciale — se lo fa davvero, il piano va aggiornato di conseguenza."
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'turing:rollback',
                $commandName,
                "Il comando Artisan registrato '{$commandName}' sembra orchestrare un rollback end-to-end dello Speciale — se lo fa davvero, il piano va aggiornato di conseguenza."
            );
        }
    }

    private function doc(): string
    {
        $content = file_get_contents(base_path('docs/06_Turing_Release/Piano_Rilascio_Preflight_Rollback_v1.0.md'));
        $this->assertIsString($content);

        return $content;
    }
}
