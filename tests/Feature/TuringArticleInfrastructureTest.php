<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TuringArticleInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_turing_article_components_are_available_for_future_deep_dive_pages(): void
    {
        $components = [
            'components/turing/article/breadcrumb.blade.php',
            'components/turing/article/hero.blade.php',
            'components/turing/article/body.blade.php',
            'components/turing/article/callout.blade.php',
            'components/turing/article/quote.blade.php',
            'components/turing/article/figure.blade.php',
            'components/turing/article/cta.blade.php',
            'components/turing/article/back-link.blade.php',
        ];

        foreach ($components as $component) {
            $this->assertFileExists(resource_path('views/'.$component));
        }
    }

    public function test_existing_turing_routes_remain_registered_and_main_page_renders(): void
    {
        $this->assertTrue(Route::has('turing'));
        $this->assertTrue(Route::has('turing.enigma'));
        $this->assertTrue(Route::has('turing.ai'));
        // La pagina Legacy (PR #44) e' la prima ad attivare effettivamente
        // il namespace <x-turing.article.*> descritto da questa classe.
        $this->assertTrue(Route::has('turing.legacy'));
        $this->assertTrue(Route::has('turing.computation'));

        $this->get(route('turing'))->assertOk();
    }

    public function test_all_turing_deep_dive_pages_render_successfully(): void
    {
        $this->get(route('turing.enigma'))->assertOk();
        $this->get(route('turing.ai'))->assertOk();
        $this->get(route('turing.legacy'))->assertOk();
        $this->get(route('turing.computation'))->assertOk();
    }

    public function test_enigma_deep_dive_page_renders_with_fallback_text_when_no_cms_data_exists(): void
    {
        // Regressione: senza un blocco editoriale 'enigma' in CMS, $enigmaBlock
        // e' un array vuoto e l'accesso a ['text'] deve ricadere sul fallback
        // invece di generare un errore fatale (era `?:`, richiede la chiave).
        $response = $this->get(route('turing.enigma'));

        $response
            ->assertOk()
            ->assertSeeText('Durante la Seconda guerra mondiale, Alan Turing contribuì al lavoro di Bletchley Park');
    }

    public function test_ai_deep_dive_page_renders_with_fallback_text_when_no_cms_data_exists(): void
    {
        $response = $this->get(route('turing.ai'));

        $response
            ->assertOk()
            ->assertSeeText('Nel 1950 Alan Turing pose una domanda destinata a cambiare il futuro della tecnologia');
    }
}
