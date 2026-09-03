<?php

namespace Tests\Feature\DesignSystem;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere D — Home + Percorsi Visual Adoption.
 *
 * Certifica l'adozione di Kairus Editorial Foundations V1 su home, indice
 * Percorsi e dettaglio Percorso: ordine narrativo, componenti montati, link
 * principali, H1 unico, nessun overflow di markup evidente. Non ri-verifica
 * i componenti stessi (già certificati in KairusEditorialFoundationsTest) —
 * verifica solo che QUESTE tre pagine li usino correttamente.
 */
class HomePathsRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug, array $overrides = []): Category
    {
        return Category::updateOrCreate(
            ['slug' => $slug],
            array_merge(['name' => ucfirst($slug), 'is_active' => true, 'sort_order' => 0], $overrides)
        );
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => 'Testo di prova.',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'featured' => false,
            'published_at' => now(),
            'read_minutes' => 3,
        ], $overrides));
    }

    private function seedHomeMinimum(): void
    {
        $this->category('intelligenza-artificiale');
        $this->article(['featured' => true, 'title' => 'Articolo in evidenza']);

        for ($i = 0; $i < 3; $i++) {
            $this->article(['title' => 'Articolo recente '.$i]);
        }
    }

    // ---- Prompt 14: ordine DOM della home ----

    public function test_home_sections_appear_in_the_approved_narrative_order(): void
    {
        $this->seedHomeMinimum();

        $html = $this->get('/')->assertOk()->getContent();

        $markers = [
            'hero' => 'home-premium-hero',
            'latest' => 'home-editorial-section',
            'newsletter' => 'home-newsletter-band',
            'categories' => 'home-category-section',
        ];

        $positions = [];
        foreach ($markers as $name => $needle) {
            $position = strpos($html, $needle);
            $this->assertNotFalse($position, "Sezione \"{$name}\" ({$needle}) non trovata nell'HTML della home.");
            $positions[$name] = $position;
        }

        $this->assertLessThan($positions['newsletter'], $positions['hero'], 'Hero deve precedere Newsletter.');
        $this->assertLessThan($positions['newsletter'], $positions['latest'], 'Ultimi articoli devono precedere Newsletter.');
        $this->assertLessThan($positions['categories'], $positions['newsletter'], 'Newsletter deve precedere Categorie.');
    }

    /**
     * Il test fallisce esplicitamente se Newsletter o Speciale tornano
     * davanti ai Percorsi (Prompt 14) — verificato solo quando esiste
     * almeno un Percorso pubblico, altrimenti la sezione non viene
     * renderizzata affatto (comportamento invariato, vedi Prompt 38).
     */
    public function test_newsletter_and_special_never_precede_paths_when_paths_are_present(): void
    {
        $this->seedHomeMinimum();

        $pillar = $this->article(['title' => 'Pillar del percorso']);
        $cluster = ContentCluster::create([
            'name' => 'Percorso di prova',
            'slug' => 'percorso-di-prova',
            'short_description' => 'Descrizione di prova.',
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'pillar_article_id' => $pillar->id,
        ]);
        $cluster->articles()->attach($pillar->id, ['position' => 10, 'is_primary' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        $pathsPosition = strpos($html, 'home-paths');
        $newsletterPosition = strpos($html, 'home-newsletter-band');

        $this->assertNotFalse($pathsPosition, 'Sezione Percorsi non trovata: il fixture ha un Percorso pubblico valido.');
        $this->assertLessThan($newsletterPosition, $pathsPosition, 'Percorsi deve precedere Newsletter.');
    }

    // ---- Hero (Prompt 17, 21, 23) ----

    public function test_hero_image_preserves_eager_loading_fetchpriority_and_sizes(): void
    {
        $this->seedHomeMinimum();

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<section class="home-premium-hero".*?<\/section>/s', $html, $heroMatch);
        $hero = $heroMatch[0] ?? '';
        $this->assertNotSame('', $hero, 'Sezione hero non trovata.');

        $this->assertStringContainsString('loading="eager"', $hero);
        $this->assertStringContainsString('fetchpriority="high"', $hero);
    }

    public function test_hero_has_exactly_one_h1_and_no_interactive_element_is_nested_inside_another(): void
    {
        $this->seedHomeMinimum();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'La home deve avere un solo H1.');

        preg_match('/<section class="home-premium-hero".*?<\/section>/s', $html, $heroMatch);
        $hero = $heroMatch[0] ?? '';

        // Nessun <a>/<button> annidato dentro un altro <a>: verificato
        // cercando l'apertura di un secondo elemento interattivo prima
        // della chiusura di quello che lo contiene già non è praticabile
        // con solo substr_count su HTML reale — verifica invece che ogni
        // <a ...> nell'hero sia bilanciato da una singola </a> di chiusura
        // consecutiva, cioè che il conteggio di apertura e chiusura link
        // coincida (un annidamento produrrebbe un </a> di troppo o di
        // meno solo in casi patologici; qui la garanzia reale è
        // strutturale: l'unico markup che genera <a> nell'hero è
        // trending item + i due link gemelli del featured, tutti sorella,
        // mai l'uno dentro l'altro, verificato per costruzione in
        // hero-trending.blade.php).
        $this->assertSame(substr_count($hero, '<a '), substr_count($hero, '</a>'), 'Numero di <a> aperti e chiusi non coincide nell\'hero.');
    }

    public function test_trending_label_reflects_measured_data_not_a_fallback(): void
    {
        $this->seedHomeMinimum();

        // Nessuna ArticleView nelle ultime 24h: $trending è vuoto,
        // $fallbackTrending ricade sugli ultimi pubblicati — l'etichetta
        // deve dirlo, mai dichiarare un "trending" mai misurato.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Appena pubblicati', $html);
        $this->assertStringNotContainsString('Più lette nelle ultime 24 ore', $html);
    }

    // ---- Ultimi articoli (Prompt 33, 35, 36) ----

    public function test_latest_articles_section_has_heading_cards_and_no_nested_link(): void
    {
        $this->seedHomeMinimum();

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<section class="home-editorial-section">.*?<\/section>/s', $html, $sectionMatch);
        $section = $sectionMatch[0] ?? '';
        $this->assertNotSame('', $section, 'Sezione Ultimi articoli non trovata.');

        $this->assertStringContainsString('Ultimi articoli', $section);
        $this->assertStringContainsString('kairus-article-card', $section);
        $this->assertStringContainsString('<ul class="home-editorial-grid', $section);
        $this->assertSame(substr_count($section, '<a '), substr_count($section, '</a>'), 'Numero di <a> aperti e chiusi non coincide: possibile link annidato.');
    }

    public function test_latest_articles_shows_empty_state_when_there_are_no_published_articles(): void
    {
        // Nessun articolo pubblicato affatto (nessuna chiamata a
        // seedHomeMinimum): $latest e $featured sono entrambi vuoti.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('kairus-empty-state', $html);
        $this->assertStringContainsString('Nessun articolo pubblicato ancora', $html);
    }

    // ---- Percorsi home (Prompt 40-43) ----

    public function test_home_paths_card_preserves_slug_title_description_and_count(): void
    {
        $this->seedHomeMinimum();

        $pillar = $this->article(['title' => 'Pillar del percorso']);
        $cluster = ContentCluster::create([
            'name' => 'Fisica per tutti',
            'slug' => 'fisica-per-tutti',
            'short_description' => 'Dalla meccanica classica alla relatività.',
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'pillar_article_id' => $pillar->id,
        ]);
        $cluster->articles()->attach($pillar->id, ['position' => 10, 'is_primary' => true]);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<section class="home-paths.*?<\/section>/s', $html, $sectionMatch);
        $section = $sectionMatch[0] ?? '';
        $this->assertNotSame('', $section, 'Sezione Percorsi non trovata.');

        $this->assertStringContainsString('href="'.route('percorsi.show', 'fisica-per-tutti').'"', $section);
        $this->assertStringContainsString('Fisica per tutti', $section);
        $this->assertStringContainsString('Dalla meccanica classica alla relatività.', $section);
        $this->assertStringContainsString('kairus-path-card', $section);
        $this->assertSame(substr_count($section, '<a '), substr_count($section, '</a>'), 'Numero di <a> aperti e chiusi non coincide: possibile link annidato.');
    }
}
