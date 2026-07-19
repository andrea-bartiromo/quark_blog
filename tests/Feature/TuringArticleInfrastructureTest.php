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
            $this->assertFileExists(resource_path('views/' . $component));
        }
    }

    public function test_existing_turing_routes_remain_registered_and_main_page_renders(): void
    {
        $this->assertTrue(Route::has('turing'));
        $this->assertTrue(Route::has('turing.enigma'));
        $this->assertTrue(Route::has('turing.ai'));

        $this->get(route('turing'))->assertOk();
    }
}
