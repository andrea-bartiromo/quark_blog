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
}
