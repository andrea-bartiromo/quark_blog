<?php

namespace Tests\Feature;

use App\Models\TuringChapterSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 61 (programma "100 cantieri Kairus"). Le fonti registrate
 * dall'editor compaiono in una sezione "Fonti" in fondo alla pagina
 * pubblica del capitolo corrispondente — solo quando lo Speciale è
 * pubblico (config('turing.chapters_public'), Cantieri 57/63) e solo
 * sul capitolo a cui appartengono.
 */
class TuringChapterSourcesPublicRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['turing.chapters_public' => true]);
    }

    public function test_a_chapter_with_no_sources_shows_no_fonti_section(): void
    {
        $html = $this->get(route('turing.enigma'))->assertOk()->getContent();

        $this->assertStringNotContainsString('turing-chapter-sources', $html);
    }

    public function test_a_source_added_for_a_chapter_appears_on_its_public_page(): void
    {
        TuringChapterSource::create([
            'chapter' => 'computation',
            'label' => 'On Computable Numbers (1936)',
            'url' => 'https://example.com/1936-paper',
            'year' => '1936',
            'sort_order' => 0,
        ]);

        $response = $this->get(route('turing.computation'));

        $response->assertOk();
        $response->assertSeeText('Fonti');
        $response->assertSeeText('On Computable Numbers (1936)');
        $response->assertSeeText('(1936)');
        $response->assertSee('https://example.com/1936-paper', false);
    }

    public function test_a_source_does_not_leak_onto_a_different_chapters_public_page(): void
    {
        TuringChapterSource::create([
            'chapter' => 'legacy',
            'label' => 'Scuse pubbliche del 2009',
            'url' => 'https://example.com/2009-apology',
            'sort_order' => 0,
        ]);

        $html = $this->get(route('turing.enigma'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Scuse pubbliche del 2009', $html);
    }

    public function test_each_of_the_five_chapters_can_render_its_own_sources(): void
    {
        $routes = [
            'turing.enigma' => 'enigma',
            'turing.ai' => 'ai',
            'turing.legacy' => 'legacy',
            'turing.computation' => 'computation',
            'turing.intelligence' => 'intelligence',
        ];

        foreach ($routes as $routeName => $chapter) {
            TuringChapterSource::create([
                'chapter' => $chapter,
                'label' => "Fonte {$chapter}",
                'url' => "https://example.com/{$chapter}",
                'sort_order' => 0,
            ]);

            $response = $this->get(route($routeName));

            $response->assertOk();
            $response->assertSeeText("Fonte {$chapter}");
        }
    }

    public function test_sources_do_not_render_while_the_special_is_not_yet_public(): void
    {
        config(['turing.chapters_public' => false]);

        TuringChapterSource::create([
            'chapter' => 'enigma',
            'label' => 'Fonte non ancora pubblica',
            'url' => 'https://example.com',
            'sort_order' => 0,
        ]);

        $html = $this->get(route('turing.enigma'))->getContent();

        $this->assertStringNotContainsString('Fonte non ancora pubblica', $html);
    }
}
