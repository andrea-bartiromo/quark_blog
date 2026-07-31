<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il rilascio pubblico dello Speciale Turing con i capitoli ancora
 * incompleti (vedi config/turing.php): /turing come landing "In arrivo",
 * i capitoli che reindirizzano invece di esporre contenuti incompleti, e i
 * meta tag della landing. Usa esplicitamente lo stato di default
 * (config('turing.chapters_public') = false, come in produzione prima del
 * rilascio) — non serve impostarlo, è già il default applicativo.
 */
class TuringReleaseGateTest extends TestCase
{
    use RefreshDatabase;

    private const CHAPTER_ROUTES = [
        'turing.enigma',
        'turing.ai',
        'turing.legacy',
        'turing.computation',
        'turing.intelligence',
    ];

    public function test_chapters_public_defaults_to_false(): void
    {
        $this->assertFalse((bool) config('turing.chapters_public'));
    }

    public function test_turing_route_responds_with_200(): void
    {
        $this->get('/turing')->assertOk();
    }

    public function test_turing_renders_the_coming_soon_landing_view(): void
    {
        $this->get(route('turing'))->assertViewIs('turing.coming-soon');
    }

    public function test_turing_landing_communicates_the_special_is_not_yet_published(): void
    {
        $this->get('/turing')
            ->assertOk()
            ->assertSeeText('Alan Turing')
            ->assertSeeText('In lavorazione');
    }

    public function test_turing_landing_previews_chapter_topics_without_linking_to_them(): void
    {
        $html = $this->get('/turing')->getContent();

        // Le anteprime dei capitoli sono card non cliccabili
        // (<x-special.feature-cards> senza 'url'): nessun link verso le
        // rotte /turing/* incomplete deve comparire nella pagina.
        $this->assertStringNotContainsString('href="'.route('turing.enigma').'"', $html);
        $this->assertStringNotContainsString('href="'.route('turing.ai').'"', $html);
        $this->assertStringNotContainsString('href="'.route('turing.legacy').'"', $html);
        $this->assertStringNotContainsString('href="'.route('turing.computation').'"', $html);
        $this->assertStringNotContainsString('href="'.route('turing.intelligence').'"', $html);
        $this->assertStringContainsString('sp-feature-card--static', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }

    public function test_turing_landing_has_exactly_one_h1(): void
    {
        $html = $this->get('/turing')->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_turing_landing_has_correct_meta_title_description_canonical_and_robots(): void
    {
        $html = $this->get('/turing')->getContent();

        $this->assertStringContainsString('<title>Speciale Turing — In arrivo — Quark</title>', $html);
        $this->assertMatchesRegularExpression(
            '#<meta name="description" content="Il nuovo Speciale di Quark[^"]*">#',
            $html
        );
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/turing').'">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);
    }

    public function test_turing_landing_has_coherent_open_graph_tags(): void
    {
        $html = $this->get('/turing')->getContent();

        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="Speciale Turing — In arrivo — Quark">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="'.url('/turing').'">', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]+turing-hero\.webp">#', $html);
    }

    public function test_each_incomplete_chapter_redirects_to_turing_with_a_302(): void
    {
        foreach (self::CHAPTER_ROUTES as $routeName) {
            $response = $this->get(route($routeName));

            $response->assertStatus(302);
            $response->assertRedirect(route('turing'));
        }
    }

    public function test_redirected_chapter_responses_do_not_leak_any_chapter_content(): void
    {
        // Una RedirectResponse non ha un body HTML da controllare nel
        // dettaglio, ma verifichiamo comunque che non sia un rendering
        // camuffato da 200 con dentro contenuti della pagina capitolo.
        foreach (self::CHAPTER_ROUTES as $routeName) {
            $response = $this->get(route($routeName));

            $this->assertNotSame(200, $response->getStatusCode());
        }
    }

    public function test_chapter_routes_still_resolve_by_name_for_future_relaunch(): void
    {
        // Le rotte restano registrate con lo stesso nome/URI: il redirect è
        // nel controller, non nella definizione della rotta, quindi non è
        // necessario toccare routes/web.php quando lo Speciale sarà pronto.
        $this->assertSame(url('/turing/enigma'), route('turing.enigma'));
        $this->assertSame(url('/turing/ai'), route('turing.ai'));
        $this->assertSame(url('/turing/legacy'), route('turing.legacy'));
        $this->assertSame(url('/turing/computation'), route('turing.computation'));
        $this->assertSame(url('/turing/intelligence'), route('turing.intelligence'));
    }

    public function test_chapter_views_still_exist_on_disk_for_future_relaunch(): void
    {
        foreach (['enigma', 'ai', 'legacy', 'computation', 'intelligence'] as $chapter) {
            $this->assertFileExists(resource_path("views/turing/{$chapter}.blade.php"));
        }

        $this->assertFileExists(resource_path('views/turing/index.blade.php'));
    }

    public function test_chapters_render_normally_again_once_the_flag_is_enabled(): void
    {
        config(['turing.chapters_public' => true]);

        foreach (self::CHAPTER_ROUTES as $routeName) {
            $this->get(route($routeName))->assertOk();
        }

        $this->get(route('turing'))->assertViewIs('turing.index');
    }
}
