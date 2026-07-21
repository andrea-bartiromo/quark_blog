<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TuringLegacyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_route_is_registered(): void
    {
        $this->assertTrue(Route::has('turing.legacy'));
    }

    public function test_legacy_page_responds_with_200(): void
    {
        $this->get(route('turing.legacy'))->assertOk();
    }

    public function test_legacy_page_renders_hero_content(): void
    {
        $response = $this->get(route('turing.legacy'));

        $response
            ->assertOk()
            ->assertSeeText('Il genio inquieto')
            ->assertSeeText('Eredità');
    }

    public function test_legacy_page_includes_a_breadcrumb_back_to_the_special(): void
    {
        $response = $this->get(route('turing.legacy'));

        $response
            ->assertOk()
            ->assertSee('turing-article-breadcrumb', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('turing').'"', false);
    }

    public function test_legacy_page_links_back_to_the_main_special_page(): void
    {
        $this->get(route('turing.legacy'))
            ->assertOk()
            ->assertSee('href="'.route('turing').'"', false)
            ->assertSeeText('Torna allo speciale');
    }

    public function test_legacy_page_links_to_the_enigma_deep_dive(): void
    {
        $this->get(route('turing.legacy'))
            ->assertOk()
            ->assertSee('href="'.route('turing.enigma').'"', false);
    }

    public function test_legacy_page_links_to_the_ai_deep_dive(): void
    {
        $this->get(route('turing.legacy'))
            ->assertOk()
            ->assertSee('href="'.route('turing.ai').'"', false);
    }

    public function test_legacy_page_renders_its_main_structural_sections(): void
    {
        $response = $this->get(route('turing.legacy'));

        $response
            ->assertOk()
            ->assertSeeText('Eredità scientifica')
            ->assertSeeText('Bletchley Park')
            ->assertSeeText('1952')
            ->assertSeeText('Il riconoscimento tardivo')
            ->assertSeeText('Memoria e simbolo');
    }

    public function test_legacy_page_renders_without_errors_when_no_optional_cms_data_exists(): void
    {
        // La vista non dipende da alcun blocco 'legacy' nel CMS: il
        // controller non passa dati opzionali, quindi il rendering deve
        // funzionare a prescindere dall'esistenza di un record SpecialPage.
        $response = $this->get(route('turing.legacy'));

        $response->assertOk();
        $response->assertDontSee('ErrorException', false);
    }
}
