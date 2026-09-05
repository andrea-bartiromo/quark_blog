<?php

namespace Tests\Feature\DesignSystem;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cantiere I — Accessibilità e responsive trasversali (Prompt 232-234).
 *
 * Congela, su tutte e sette le superfici pubbliche già adottate dal
 * sistema Kairus (home, percorsi indice/dettaglio, articolo, notizie,
 * categoria, ricerca), gli invarianti trasversali verificati
 * empiricamente con Playwright durante l'audit di questo cantiere:
 * un solo H1 per pagina, uno skip-link funzionante, nessun link
 * annidato in un altro link, e ogni immagine con l'attributo alt.
 * Non sostituisce l'audit Playwright (qui non si verifica overflow,
 * contrasto o focus-visible reale) — congela invece ciò che è
 * verificabile in modo stabile dalla sola risposta HTTP, cosi' una
 * regressione futura su una qualunque delle sette superfici viene
 * colta dalla suite PHP ordinaria.
 */
class PublicSurfacesAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $author, array $overrides = []): Article
    {
        Category::updateOrCreate(['slug' => 'fisica'], ['name' => 'Fisica', 'is_active' => true, 'sort_order' => 0]);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova per la QA trasversale',
            'slug' => 'articolo-qa-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo di prova.</p>',
            'category' => 'fisica',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'read_minutes' => 4,
        ], $overrides));
    }

    /** @return array<string, array{0: string}> */
    public static function surfaceProvider(): array
    {
        return [
            'home' => ['home'],
            'percorsi index' => ['percorsi-index'],
            'percorso show' => ['percorso-show'],
            'articolo' => ['articolo'],
            'notizie' => ['notizie'],
            'categoria' => ['categoria'],
            'ricerca' => ['ricerca'],
        ];
    }

    private function urlFor(string $surface): string
    {
        $author = $this->author();
        $article = $this->article($author, ['featured' => true]);

        return match ($surface) {
            'home' => route('home'),
            'percorsi-index' => route('percorsi.index'),
            'percorso-show' => (function () use ($article) {
                $cluster = ContentCluster::factory()->create([
                    'is_active' => true,
                    'publish_at' => now()->subDay(),
                ]);
                $cluster->articles()->attach($article->id, ['position' => 0, 'is_primary' => true]);

                return route('percorsi.show', $cluster->slug);
            })(),
            'articolo' => route('articolo', $article->slug),
            'notizie' => route('notizie'),
            'categoria' => route('categoria', 'fisica'),
            'ricerca' => route('ricerca'),
        };
    }

    #[DataProvider('surfaceProvider')]
    public function test_surface_has_exactly_one_h1(string $surface): void
    {
        $html = $this->get($this->urlFor($surface))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), "La superficie \"{$surface}\" deve avere esattamente un H1.");
    }

    #[DataProvider('surfaceProvider')]
    public function test_surface_has_a_working_skip_link(string $surface): void
    {
        $html = $this->get($this->urlFor($surface))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<a\b(?=[^>]*\bclass="skip-link")(?=[^>]*\bhref="#([\w-]+)")[^>]*>/', $html, "La superficie \"{$surface}\" deve avere uno skip-link.");
        preg_match('/<a\b(?=[^>]*\bclass="skip-link")(?=[^>]*\bhref="#([\w-]+)")[^>]*>/', $html, $m);
        $this->assertStringContainsString('id="'.$m[1].'"', $html, "Lo skip-link della superficie \"{$surface}\" deve puntare a un id realmente presente.");
    }

    #[DataProvider('surfaceProvider')]
    public function test_surface_never_nests_a_link_inside_another_link(string $surface): void
    {
        $html = $this->get($this->urlFor($surface))->assertOk()->getContent();

        preg_match_all('/<a\b[^>]*>(.*?)<\/a>/is', $html, $matches);
        foreach ($matches[1] as $inner) {
            $this->assertDoesNotMatchRegularExpression('/<a\b/i', $inner, "La superficie \"{$surface}\" non deve mai annidare un <a> dentro un altro <a>.");
        }
    }

    #[DataProvider('surfaceProvider')]
    public function test_surface_gives_every_image_an_alt_attribute(string $surface): void
    {
        $html = $this->get($this->urlFor($surface))->assertOk()->getContent();

        preg_match_all('/<img\b[^>]*>/i', $html, $matches);
        foreach ($matches[0] as $imgTag) {
            $this->assertMatchesRegularExpression('/\balt="/i', $imgTag, "La superficie \"{$surface}\" ha un'immagine senza attributo alt: {$imgTag}");
        }
    }

    /**
     * Adozione coerente: ogni superficie monta almeno un componente del
     * sistema Kairus (una classe .kairus-*) — congela che nessuna delle
     * sette torni indietro a un markup puramente legacy.
     */
    public function test_every_surface_still_adopts_at_least_one_kairus_component(): void
    {
        foreach (array_column(self::surfaceProvider(), 0) as $surface) {
            $html = $this->get($this->urlFor($surface))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/class="[^"]*\bkairus-/', $html, "La superficie \"{$surface}\" deve montare almeno un componente Kairus.");
        }
    }
}
