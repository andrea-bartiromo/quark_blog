<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TuringComputationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_computation_route_is_registered(): void
    {
        $this->assertTrue(Route::has('turing.computation'));
    }

    public function test_computation_page_responds_with_200(): void
    {
        $this->get(route('turing.computation'))->assertOk();
    }

    public function test_computation_page_renders_the_main_title(): void
    {
        $this->get(route('turing.computation'))
            ->assertOk()
            ->assertSeeText('La macchina universale e l’idea moderna di programma');
    }

    public function test_computation_page_covers_the_core_concepts(): void
    {
        $response = $this->get(route('turing.computation'));

        $response
            ->assertOk()
            ->assertSeeText('La macchina di Turing')
            ->assertSeeText('Algoritmi e computabilità')
            ->assertSeeText('Una macchina capace di simularne altre')
            ->assertSeeText('I limiti del calcolo');
    }

    public function test_computation_page_links_back_to_the_main_special_page(): void
    {
        $this->get(route('turing.computation'))
            ->assertOk()
            ->assertSee('href="'.route('turing').'"', false)
            ->assertSeeText('Torna allo speciale');
    }

    public function test_computation_page_does_not_render_nested_main_elements(): void
    {
        $html = $this->get(route('turing.computation'))->getContent();

        $this->assertSame(1, substr_count($html, '<main'));
    }

    public function test_computation_page_includes_a_breadcrumb(): void
    {
        $this->get(route('turing.computation'))
            ->assertOk()
            ->assertSee('turing-article-breadcrumb', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_main_turing_page_links_to_the_computation_detail_page(): void
    {
        $this->get(route('turing'))
            ->assertOk()
            ->assertSee('href="/turing/computation"', false)
            ->assertSeeText('Scopri la macchina universale');
    }

    public function test_main_turing_page_no_longer_uses_a_self_referencing_anchor_for_computation(): void
    {
        $this->get(route('turing'))
            ->assertOk()
            ->assertDontSee('href="#macchina-universale"', false);
    }

    public function test_other_turing_pages_still_respond_successfully(): void
    {
        $this->get(route('turing'))->assertOk();
        $this->get(route('turing.enigma'))->assertOk();
        $this->get(route('turing.ai'))->assertOk();
        $this->get(route('turing.legacy'))->assertOk();
    }

    public function test_computation_page_does_not_link_to_the_not_yet_existing_intelligence_page(): void
    {
        // La pagina /turing/intelligence e' esplicitamente fuori scope di
        // questa PR (sara' oggetto di una PR successiva): il collegamento
        // "Dal calcolo all'intelligenza" deve puntare a /turing/ai finche'
        // quella route non esiste, mai a una route inesistente.
        $this->assertFalse(Route::has('turing.intelligence'));

        $this->get(route('turing.computation'))
            ->assertOk()
            ->assertDontSee('href="/turing/intelligence"', false)
            ->assertSeeText('Dal calcolo all’intelligenza')
            ->assertSee('href="'.route('turing.ai').'"', false);
    }

    public function test_computation_page_renders_without_errors_when_no_optional_cms_data_exists(): void
    {
        $response = $this->get(route('turing.computation'));

        $response->assertOk();
        $response->assertDontSee('ErrorException', false);
    }

    public function test_cms_content_unrelated_to_computation_does_not_break_the_main_page_or_its_link(): void
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
            ->assertDontSeeText('La macchina universale e l’idea moderna di programma');
    }
}
