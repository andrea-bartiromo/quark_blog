<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * B-62 — il prototipo Atlante visuale non deve avere alcuna route
 * pubblica finché non esiste una decisione GO per il pilot (B-65).
 */
class AtlanteVisualePrototypeNotRoutedTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_points_to_the_prototype_view(): void
    {
        $viewNames = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getActionName())
            ->filter()
            ->implode(' ');

        $this->assertStringNotContainsString('atlante-visuale', $viewNames);
    }

    public function test_the_prototype_view_file_exists_but_is_not_publicly_reachable(): void
    {
        $this->assertFileExists(resource_path('views/prototypes/atlante-visuale-tavola.blade.php'));

        foreach (['/atlante-visuale', '/prototypes/atlante-visuale-tavola'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }
}
