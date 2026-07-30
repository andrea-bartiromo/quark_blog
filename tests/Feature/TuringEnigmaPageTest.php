<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TuringEnigmaPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_enigma_route_is_registered(): void
    {
        $this->assertTrue(Route::has('turing.enigma'));
    }

    public function test_enigma_page_responds_with_200(): void
    {
        $this->get(route('turing.enigma'))->assertOk();
    }

    public function test_enigma_page_renders_the_main_title(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSeeText('Enigma, Ultra e la guerra invisibile');
    }

    public function test_enigma_hero_image_points_to_an_existing_asset(): void
    {
        $path = 'turing/enigma/turing-enigma-background.webp';

        $this->assertFileExists(public_path('assets/img/'.$path));

        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('assets/img/'.$path, false);
    }

    public function test_enigma_figure_image_points_to_an_existing_asset(): void
    {
        $path = 'turing/enigma/turing-enigma-panel.webp';

        $this->assertFileExists(public_path('assets/img/'.$path));

        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('assets/img/'.$path, false);
    }

    public function test_enigma_page_covers_the_core_concepts(): void
    {
        $response = $this->get(route('turing.enigma'));

        $response
            ->assertOk()
            ->assertSeeText('Uno spazio di configurazioni enorme')
            ->assertSeeText('Piccoli appigli linguistici')
            ->assertSeeText('L’automazione entra in gioco')
            ->assertSeeText('Non forza bruta, ma intelligenza organizzata');
    }

    public function test_enigma_page_links_back_to_the_main_special_page(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('href="'.route('turing').'"', false)
            ->assertSeeText('Torna allo speciale');
    }

    public function test_enigma_page_does_not_render_nested_main_elements(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        $this->assertSame(1, substr_count($html, '<main'));
    }

    public function test_enigma_page_includes_a_breadcrumb(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('turing-article-breadcrumb', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_main_turing_page_links_to_the_enigma_detail_page(): void
    {
        $this->get(route('turing'))
            ->assertOk()
            ->assertSee('href="/turing/enigma"', false)
            ->assertSeeText('Esplora Enigma');
    }

    public function test_enigma_page_links_to_computation_and_intelligence(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('href="'.route('turing.computation').'"', false)
            ->assertSee('href="'.route('turing.intelligence').'"', false);
    }

    public function test_other_turing_pages_still_respond_successfully(): void
    {
        $this->get(route('turing'))->assertOk();
        $this->get(route('turing.computation'))->assertOk();
        $this->get(route('turing.intelligence'))->assertOk();
        $this->get(route('turing.ai'))->assertOk();
        $this->get(route('turing.legacy'))->assertOk();
    }

    public function test_enigma_page_renders_without_errors_when_no_optional_cms_data_exists(): void
    {
        $response = $this->get(route('turing.enigma'));

        $response->assertOk();
        $response->assertDontSee('ErrorException', false);
    }

    public function test_cms_content_unrelated_to_enigma_does_not_break_the_main_page_or_its_link(): void
    {
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'description' => 'Speciale editoriale dedicato ad Alan Turing.',
            'content' => [
                'editorial_blocks' => [
                    [
                        'enabled' => true,
                        'key' => 'cms-block',
                        'layout' => 'image_left',
                        'kicker' => 'CMS',
                        'title' => 'Blocco CMS personalizzato',
                        'text' => 'Testo personalizzato salvato dal CMS.',
                        'link_label' => 'Azione CMS',
                        'link_url' => '#cms-block',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('Blocco CMS personalizzato')
            ->assertDontSeeText('Enigma, Ultra e la guerra invisibile');
    }
}
