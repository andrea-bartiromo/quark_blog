<?php

namespace Tests\Feature\Console;

use App\Services\PublicPages\InProcessPageFetcher;
use App\Services\PublicPages\WcagInternalAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Cantiere 29 (programma 100-cantieri Kairus): pages:wcag-audit è di sola
 * lettura — non è un gate di rilascio, è un catalogo di findings per un
 * editore/operatore. Girato per davvero ha gia' trovato due regressioni
 * genuine, entrambe corrette nella stessa PR:
 * - un salto di livello heading (h1 -> h3) nella pagina Contatti
 *   (`resources/views/contatti.blade.php`), fuori dal perimetro delle 7
 *   superfici mai controllate manualmente in Cantiere I — corretto h3 ->
 *   h2, con lo stile CSS `.premium-widget` esteso per non alterare la
 *   resa visiva;
 * - la home priva di QUALUNQUE `<h1>` quando non esiste ancora un
 *   articolo pubblicato (`$featured` nullo — stato legittimo, es. un
 *   sito appena installato): l'unico `<h1>` della home viveva dentro
 *   `home/partials/hero-trending.blade.php`, interamente condizionato a
 *   `@if($featured)`. Corretto con un `<h1 class="sr-only">` di
 *   fallback in `resources/views/home.blade.php`, visibile solo
 *   nell'esatto stato in cui la sezione con l'h1 reale non renderizza.
 */
class WcagInternalAuditReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_no_findings_on_the_real_application(): void
    {
        $this->artisan('pages:wcag-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun finding WCAG rilevato sulle pagine verificate.');
    }

    public function test_json_output_is_valid_and_succeeds(): void
    {
        $this->artisan('pages:wcag-audit', ['--json' => true])->assertExitCode(0);
    }

    /**
     * Regressione: la pagina Contatti aveva un <h1> seguito direttamente
     * da due <h3> (nessun <h2> intermedio) — un salto di livello heading
     * mai rilevato dall'audit manuale una tantum di Cantiere I, che non
     * copriva questa pagina. Corretto in resources/views/contatti.blade.php
     * (h3 -> h2) nella stessa PR che ha introdotto questo comando.
     */
    public function test_the_contatti_page_has_no_heading_level_jump(): void
    {
        $results = app(WcagInternalAudit::class)->audit();
        $contatti = collect($results)->firstWhere('key', 'contatti');

        $this->assertNotNull($contatti);
        $this->assertSame([], $contatti['findings']);
    }

    /**
     * Regressione: senza alcun articolo pubblicato (stato legittimo,
     * mai un errore — RefreshDatabase non semina articoli), l'unico
     * <h1> della home viveva dentro home/partials/hero-trending.blade.php,
     * interamente condizionato a @if($featured): la home restava allora
     * priva di qualunque <h1>. Corretto con un fallback in
     * resources/views/home.blade.php.
     */
    public function test_the_home_page_has_an_h1_even_without_a_featured_article(): void
    {
        $results = app(WcagInternalAudit::class)->audit();
        $home = collect($results)->firstWhere('key', 'home');

        $this->assertNotNull($home);
        $this->assertSame([], $home['findings']);
    }

    public function test_json_output_fails_when_a_page_returns_a_non_200_status(): void
    {
        $fake = new class extends InProcessPageFetcher
        {
            public function fetch(string $url): Response
            {
                return new Response('errore', 500);
            }
        };
        $this->app->instance(InProcessPageFetcher::class, $fake);

        $this->artisan('pages:wcag-audit', ['--json' => true])->assertExitCode(1);
    }
}
