<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S5: la route /up nativa di Laravel (bootstrap/app.php, health: '/up')
 * di default è pura liveness — nessun listener su DiagnosingHealth,
 * risponde 200 anche a DB offline o storage non scrivibile. Questi test
 * verificano l'estensione a readiness reale (App\Listeners\
 * CheckApplicationHealth) e il contratto di sicurezza della risposta.
 *
 * Il "no leak" del framework per /up dipende da APP_DEBUG: con debug=true
 * (default locale/testing) Laravel ri-lancia l'eccezione originale nella
 * risposta — comportamento voluto per lo sviluppo locale, non un bug. In
 * produzione APP_DEBUG=false è garantito da deploy.sh (docs/DEPLOYMENT.md),
 * quindi i test di sicurezza qui forzano esplicitamente config(['app.debug'
 * => false]) per verificare il comportamento realmente rilevante.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalDefaultConnection = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefaultConnection = config('database.default');
    }

    protected function tearDown(): void
    {
        // Ripristinato esplicitamente al connection name reale del run
        // (sqlite in locale, mariadb quando lanciato con quell'env — mai
        // hardcoded): senza questo, un test che rompe la connessione di
        // default lascerebbe quello stato a config() per il resto del
        // processo PHPUnit, facendo fallire il setUp() (avvio transazione
        // di RefreshDatabase) di ogni test successivo nella stessa run —
        // non solo in questa classe.
        if ($this->originalDefaultConnection !== null) {
            config(['database.default' => $this->originalDefaultConnection]);
        }

        parent::tearDown();
    }

    private function breakDatabaseConnection(): void
    {
        config([
            'database.default' => 'health_check_broken_connection_test',
            'database.connections.health_check_broken_connection_test' => [
                'driver' => 'sqlite',
                'database' => '/nonexistent/path/that/cannot/possibly/exist/db.sqlite',
                'prefix' => '',
            ],
        ]);
        DB::purge('health_check_broken_connection_test');
    }

    public function test_health_check_succeeds_when_dependencies_are_healthy(): void
    {
        $response = $this->getJson('/up');

        $response->assertOk();
        $response->assertJson(['status' => 'up']);
    }

    public function test_health_check_has_negligible_query_overhead(): void
    {
        DB::enableQueryLog();
        $this->getJson('/up')->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // DB::connection()->getPdo() stabilisce/riusa la connessione senza
        // eseguire alcuna query — il conteggio atteso è 0, non "basso".
        $this->assertSame(0, $count, 'Il controllo di readiness ha introdotto query reali: non era l\'intento (overhead trascurabile, FASE 6).');
    }

    public function test_health_check_returns_failure_when_database_is_unreachable_in_production_mode(): void
    {
        config(['app.debug' => false]);
        $this->breakDatabaseConnection();

        $response = $this->getJson('/up');

        $response->assertStatus(500);
        $response->assertJson(['status' => 'down']);
    }

    public function test_health_check_returns_failure_when_storage_is_not_writable(): void
    {
        config(['app.debug' => false]);
        $originalStoragePath = storage_path();
        $this->app->useStoragePath('/nonexistent/storage/path/for/health/check/test');

        try {
            $response = $this->getJson('/up');

            $response->assertStatus(500);
            $response->assertJson(['status' => 'down']);
        } finally {
            $this->app->useStoragePath($originalStoragePath);
        }
    }

    public function test_health_check_response_never_leaks_exception_details_or_internal_paths_in_production_mode(): void
    {
        config(['app.debug' => false]);
        $this->breakDatabaseConnection();

        $jsonResponse = $this->getJson('/up');
        $jsonResponse->assertStatus(500);
        $jsonBody = $jsonResponse->getContent();

        $this->assertStringNotContainsString('nonexistent/path', $jsonBody);
        $this->assertStringNotContainsString('database_unreachable', $jsonBody);
        $this->assertStringNotContainsString('RuntimeException', $jsonBody);
        $this->assertStringNotContainsString('SQLSTATE', $jsonBody);

        $htmlResponse = $this->get('/up');
        $htmlResponse->assertStatus(500);
        $htmlBody = $htmlResponse->getContent();

        $this->assertStringNotContainsString('nonexistent/path', $htmlBody);
        $this->assertStringNotContainsString('database_unreachable', $htmlBody);
        $this->assertStringNotContainsString('RuntimeException', $htmlBody);
        $this->assertStringNotContainsString('SQLSTATE', $htmlBody);
        $this->assertStringNotContainsString(base_path(), $htmlBody);
    }
}
