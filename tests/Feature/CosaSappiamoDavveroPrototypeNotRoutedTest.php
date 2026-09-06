<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * B-42 — il prototipo "Cosa sappiamo davvero" non deve avere alcuna route
 * pubblica finché non esiste una decisione GO per il pilot (B-45). Questo
 * test è una difesa in profondità contro un futuro collegamento
 * accidentale della view a una rotta.
 */
class CosaSappiamoDavveroPrototypeNotRoutedTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_points_to_the_prototype_view(): void
    {
        $viewNames = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getActionName())
            ->filter()
            ->implode(' ');

        $this->assertStringNotContainsString('cosa-sappiamo-davvero', $viewNames);
    }

    public function test_the_prototype_view_file_exists_but_is_not_publicly_reachable(): void
    {
        $this->assertFileExists(resource_path('views/prototypes/cosa-sappiamo-davvero.blade.php'));

        // Nessun path plausibile per questo prototipo deve rispondere 200.
        foreach (['/cosa-sappiamo-davvero', '/prototypes/cosa-sappiamo-davvero'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    /**
     * Prompt 121 (150-prompt program, riesame gate): questo prototipo
     * riproduceva staticamente l'output atteso di
     * <x-article.primary-sources> perché quel componente non esisteva
     * ancora su questo branch. Ora esiste su main (PR #532) e questo file
     * usa quello reale — un test di solo routing (sopra) non
     * renderizzerebbe mai la view, quindi non avrebbe rilevato un uso
     * scorretto del componente (prop mancante, tipo sbagliato). Questo
     * lo fa, direttamente.
     */
    public function test_the_prototype_renders_using_the_real_primary_sources_component(): void
    {
        $html = view('prototypes.cosa-sappiamo-davvero')->render();

        // Il livello esatto dell'heading (h2 vs h3) è tracciato e corretto
        // separatamente sul branch docs/measurement-closeout — non
        // duplicato qui: questo test verifica solo che il componente
        // REALE sia davvero quello a renderizzare (id, contenuto, lista),
        // qualunque sia il suo tag di heading su questo branch.
        $this->assertMatchesRegularExpression(
            '/<h[23] id="article-primary-sources-heading">Fonti primarie<\/h[23]>/',
            $html
        );
        $this->assertStringContainsString('[URL fonte di esempio]', $html);
        $this->assertStringContainsString('[Fonte testuale di esempio]', $html);
    }
}
