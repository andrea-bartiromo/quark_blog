<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S5 FASE 5: verifica che un errore reale (non simulato via mock, un vero
 * QueryException su una pagina pubblica reale) non esponga mai stack
 * trace/SQL/percorsi interni quando APP_DEBUG=false — la garanzia che
 * conta davvero, distinta da bootstrap/app.php's custom render() per
 * 404/403/500 HttpException, che non intercetta affatto un'eccezione
 * generica non-HTTP come una QueryException.
 */
class ProductionSafeErrorResponsesTest extends TestCase
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
        if ($this->originalDefaultConnection !== null) {
            config(['database.default' => $this->originalDefaultConnection]);
        }

        parent::tearDown();
    }

    public function test_unhandled_database_exception_on_a_real_page_does_not_leak_sql_or_stack_trace_in_production_mode(): void
    {
        config(['app.debug' => false]);
        config([
            'database.default' => 'db_error_test_connection',
            'database.connections.db_error_test_connection' => [
                'driver' => 'sqlite',
                'database' => '/nonexistent/path/for/db-error-test/db.sqlite',
                'prefix' => '',
            ],
        ]);
        DB::purge('db_error_test_connection');

        $response = $this->get('/notizie');

        $response->assertStatus(500);
        $body = $response->getContent();

        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('QueryException', $body);
        $this->assertStringNotContainsString('nonexistent/path', $body);
        $this->assertStringNotContainsString(base_path(), $body);
        $this->assertStringNotContainsString('select * from', $body);
    }

    public function test_nonexistent_article_slug_returns_404_not_a_soft_200(): void
    {
        $response = $this->get('/articolo/questo-slug-non-esiste-mai-'.uniqid());

        $response->assertStatus(404);
    }

    public function test_nonexistent_category_returns_404_not_a_soft_200(): void
    {
        $response = $this->get('/categoria/questa-categoria-non-esiste-mai-'.uniqid());

        $response->assertStatus(404);
    }

    public function test_404_response_does_not_leak_internal_paths(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/articolo/questo-slug-non-esiste-mai-'.uniqid());

        $response->assertStatus(404);
        $this->assertStringNotContainsString(base_path(), $response->getContent());
    }

    public function test_a_csrf_token_mismatch_produces_419_without_leaking_internals_in_production_mode(): void
    {
        config(['app.debug' => false]);

        // Non testabile con $this->post() diretto: PreventRequestForgery::
        // runningUnitTests() (vendor/laravel/framework/.../Http/Middleware/
        // PreventRequestForgery.php) bypassa DELIBERATAMENTE la verifica CSRF
        // per ogni richiesta HTTP di test PHPUnit (runningInConsole() &&
        // runningUnitTests(), entrambi veri sotto `php artisan test`) — non
        // un bug nostro. Confermato manualmente anche fuori da PHPUnit
        // (php artisan tinker, richiesta reale attraverso il kernel HTTP):
        // 419, nessun redirect — il middleware stesso funziona correttamente
        // ed è responsabilità/codice del framework, non nostro.
        //
        // Quello che è invece responsabilità della nostra app, e quindi ciò
        // che questo test verifica: come TokenMismatchException viene
        // renderizzata in produzione. Laravel la mappa internamente a un
        // HttpException(419) (Handler.php) PRIMA di passarla al nostro
        // render() custom in bootstrap/app.php — che intercetta solo
        // 404/403/500 e quindi la lascia al default Laravel, esattamente
        // come QueryException nel test sopra.
        $response = app(ExceptionHandler::class)->render(
            Request::create('/newsletter/subscribe', 'POST'),
            new TokenMismatchException('CSRF token mismatch.')
        );

        $this->assertSame(419, $response->getStatusCode());
        $body = $response->getContent();
        $this->assertStringNotContainsString(base_path(), $body);
        $this->assertStringNotContainsString('TokenMismatchException', $body);
    }

    public function test_exceeding_the_newsletter_subscribe_rate_limit_returns_429_not_a_leak(): void
    {
        config(['app.debug' => false]);

        // throttle:5,1 sulla rotta (routes/web.php) — la 6esima richiesta
        // nello stesso minuto deve ricevere 429, mai un errore generico o
        // un 200 silenzioso.
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['_token' => 'test-csrf-token'])
                ->post('/newsletter/subscribe', ['email' => "test{$i}@example.test", '_token' => 'test-csrf-token']);
        }

        $response = $this->withSession(['_token' => 'test-csrf-token'])
            ->post('/newsletter/subscribe', ['email' => 'test-over-limit@example.test', '_token' => 'test-csrf-token']);

        $response->assertStatus(429);
        $this->assertStringNotContainsString(base_path(), $response->getContent());
    }
}
