<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TuringIntelligencePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_intelligence_route_is_registered(): void
    {
        $this->assertTrue(Route::has('turing.intelligence'));
    }

    public function test_intelligence_page_responds_with_200(): void
    {
        $this->get(route('turing.intelligence'))->assertOk();
    }

    public function test_intelligence_page_renders_the_main_title(): void
    {
        $this->get(route('turing.intelligence'))
            ->assertOk()
            ->assertSeeText('Il gioco dell’imitazione e la domanda sulle macchine pensanti');
    }

    public function test_intelligence_hero_image_points_to_an_existing_asset(): void
    {
        $path = 'turing/backgrounds/turing-test-background.webp';

        $this->assertFileExists(public_path('assets/img/'.$path));

        $this->get(route('turing.intelligence'))
            ->assertOk()
            ->assertSee('assets/img/'.$path, false);
    }

    public function test_intelligence_page_covers_the_core_concepts(): void
    {
        $response = $this->get(route('turing.intelligence'));

        $response
            ->assertOk()
            ->assertSeeText('Il gioco dell’imitazione')
            ->assertSeeText('Cosa il test misura — e cosa no')
            ->assertSeeText('Turing rispose per primo ai suoi critici')
            ->assertSeeText('Una domanda tornata attuale, non risolta');
    }

    public function test_intelligence_page_links_back_to_the_main_special_page(): void
    {
        $this->get(route('turing.intelligence'))
            ->assertOk()
            ->assertSee('href="'.route('turing').'"', false)
            ->assertSeeText('Torna allo speciale');
    }

    public function test_intelligence_page_does_not_render_nested_main_elements(): void
    {
        $html = $this->get(route('turing.intelligence'))->getContent();

        $this->assertSame(1, substr_count($html, '<main'));
    }

    public function test_intelligence_page_includes_a_breadcrumb(): void
    {
        $this->get(route('turing.intelligence'))
            ->assertOk()
            ->assertSee('turing-article-breadcrumb', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_intelligence_page_links_to_the_computation_and_ai_pages(): void
    {
        $response = $this->get(route('turing.intelligence'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('turing.computation').'"', false)
            // Percorso canonico letterale, non route('turing.ai'): quel nome e'
            // duplicato da App\Providers\TuringServiceProvider (route /turing/ia,
            // registrata dopo, vince la risoluzione) - bug preesistente,
            // gia' verificato nella PR #45, non corretto qui.
            ->assertSee('href="/turing/ai"', false)
            ->assertDontSee('href="/turing/ia"', false);
    }

    public function test_main_turing_page_links_to_the_intelligence_detail_page(): void
    {
        $this->get(route('turing'))
            ->assertOk()
            ->assertSee('href="/turing/intelligence"', false)
            ->assertSeeText('Leggi la domanda');
    }

    public function test_main_turing_page_no_longer_uses_a_self_referencing_anchor_for_test_turing(): void
    {
        $this->get(route('turing'))
            ->assertOk()
            ->assertDontSee('href="#test-turing"', false);
    }

    public function test_other_turing_pages_still_respond_successfully(): void
    {
        $this->get(route('turing'))->assertOk();
        $this->get(route('turing.enigma'))->assertOk();
        $this->get(route('turing.ai'))->assertOk();
        $this->get(route('turing.legacy'))->assertOk();
        $this->get(route('turing.computation'))->assertOk();
    }

    public function test_intelligence_page_renders_without_errors_when_no_optional_cms_data_exists(): void
    {
        $response = $this->get(route('turing.intelligence'));

        $response->assertOk();
        $response->assertDontSee('ErrorException', false);
    }

    public function test_cms_content_unrelated_to_intelligence_does_not_break_the_main_page_or_its_link(): void
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
            ->assertDontSeeText('Il gioco dell’imitazione e la domanda sulle macchine pensanti');
    }
}
