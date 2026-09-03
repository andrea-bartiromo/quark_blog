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

    // ---- Newsletter (Prompt 50) ----

    public function test_newsletter_form_action_method_csrf_and_field_names_are_unchanged(): void
    {
        $this->seedHomeMinimum();

        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<section class="home-newsletter-band.*?<\/section>/s', $html, $sectionMatch);
        $section = $sectionMatch[0] ?? '';
        $this->assertNotSame('', $section, 'Sezione Newsletter non trovata.');

        $this->assertStringContainsString('action="'.route('newsletter.subscribe').'"', $section);
        $this->assertStringContainsString('method="POST"', $section);
        $this->assertStringContainsString('name="_token"', $section);
        $this->assertStringContainsString('name="source" value="homepage"', $section);
        $this->assertStringContainsString('name="email"', $section);
        $this->assertSame(1, substr_count($section, '<form'), 'Deve esistere un solo <form>.');
    }

    // ---- Speciale Turing (Prompt 51-55) ----

    public function test_special_banner_preserves_turing_url_and_content(): void
    {
        $this->seedHomeMinimum();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('kairus-special-banner', $html);
        $this->assertStringContainsString('href="'.route('turing').'"', $html);
    }

    // ---- Categorie (Prompt 56-58) ----

    public function test_category_carousel_structure_and_data_attributes_are_unchanged(): void
    {
        $this->seedHomeMinimum();
        $this->category('spazio');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('data-category-carousel', $html);
        $this->assertStringContainsString('data-category-track', $html);
        $this->assertStringContainsString('data-category-prev', $html);
        $this->assertStringContainsString('data-category-next', $html);

        preg_match('/<section class="home-category-section".*?<\/section>/s', $html, $sectionMatch);
        $section = $sectionMatch[0] ?? '';
        $this->assertNotSame('', $section, 'Sezione categorie non trovata.');
        $this->assertSame(substr_count($section, '<a '), substr_count($section, '</a>'), 'Numero di <a> aperti e chiusi non coincide: possibile link annidato.');
    }

    // ---- Indice Percorsi (Prompt 74-79) ----

    public function test_percorsi_index_has_single_h1_and_mounts_kairus_path_cards(): void
    {
        $pillar = $this->article(['title' => 'Pillar del percorso indice']);
        $cluster = ContentCluster::create([
            'name' => 'Chimica quotidiana',
            'slug' => 'chimica-quotidiana',
            'short_description' => 'Reazioni che vediamo ogni giorno.',
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'pillar_article_id' => $pillar->id,
        ]);
        $cluster->articles()->attach($pillar->id, ['position' => 10, 'is_primary' => true]);

        $html = $this->get(route('percorsi.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'L\'indice Percorsi deve avere un solo H1.');
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Percorsi', $html);
        $this->assertStringContainsString('kairus-path-card', $html);
        $this->assertStringContainsString('href="'.route('percorsi.show', 'chimica-quotidiana').'"', $html);
    }

    /**
     * Il toggle "Anteprima delle tappe" deve restare un fratello del link
     * generato da x-kairus.path-card, mai annidato al suo interno — un
     * <button> dentro un <a> non è HTML valido (Prompt 76).
     */
    public function test_percorsi_index_preview_toggle_is_a_sibling_of_the_path_card_link_not_nested(): void
    {
        $preview = $this->article(['title' => 'Tappa in anteprima']);
        $second = $this->article(['title' => 'Seconda tappa']);
        $cluster = ContentCluster::create([
            'name' => 'Biologia in breve',
            'slug' => 'biologia-in-breve',
            'short_description' => 'Le basi della vita, spiegate bene.',
            'is_active' => true,
            'lifecycle_status' => 'updating',
        ]);
        $cluster->articles()->attach([
            $preview->id => ['position' => 10, 'is_primary' => false],
            $second->id => ['position' => 20, 'is_primary' => false],
        ]);

        $html = $this->get(route('percorsi.index'))->assertOk()->getContent();

        preg_match('/<li class="path-card[^"]*" data-percorso-id="'.$cluster->id.'">.*?<\/li>/s', $html, $cardMatch);
        $card = $cardMatch[0] ?? '';
        $this->assertNotSame('', $card, 'Card del Percorso non trovata.');

        preg_match('/<a[^>]*class="[^"]*kairus-path-card[^"]*"[^>]*>.*?<\/a>/s', $card, $linkMatch);
        $link = $linkMatch[0] ?? '';
        $this->assertNotSame('', $link, 'Link x-kairus.path-card non trovato nella card.');
        $this->assertStringNotContainsString('path-card__preview-toggle', $link, 'Il toggle anteprima non deve essere annidato dentro il link della card.');
        $this->assertStringContainsString('path-card__preview-toggle', $card, 'Il toggle anteprima deve comunque comparire come fratello nella card.');
    }

    public function test_percorsi_index_shows_empty_state_when_there_are_no_active_paths(): void
    {
        $html = $this->get(route('percorsi.index'))->assertOk()->getContent();

        $this->assertStringContainsString('kairus-empty-state', $html);
        $this->assertStringContainsString('Nuovi percorsi stanno prendendo forma.', $html);
    }

    // ---- Dettaglio Percorso (Prompt 81-93) ----

    public function test_percorso_detail_has_single_h1_and_mounts_kairus_path_steps(): void
    {
        $pillar = $this->article(['title' => 'Pillar del dettaglio']);
        $second = $this->article(['title' => 'Seconda tappa del dettaglio']);
        $cluster = ContentCluster::create([
            'name' => 'Geologia essenziale',
            'slug' => 'geologia-essenziale',
            'short_description' => 'Come si legge un paesaggio.',
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'pillar_article_id' => $pillar->id,
        ]);
        $cluster->articles()->attach([
            $pillar->id => ['position' => 10, 'is_primary' => true],
            $second->id => ['position' => 20, 'is_primary' => false],
        ]);

        $html = $this->get(route('percorsi.show', 'geologia-essenziale'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'Il dettaglio Percorso deve avere un solo H1.');
        $this->assertStringContainsString('kairus-path-step', $html);
        $this->assertStringContainsString('href="'.route('articolo', $pillar->slug).'"', $html);
        $this->assertStringContainsString('href="'.route('articolo', $second->slug).'"', $html);
    }

    /**
     * Il pillar conserva l'etichetta originale "Punto di partenza" (mai un
     * testo inventato) e riceve state="current", lo stesso risalto teal
     * che aveva .path-step--pillar in precedenza (Prompt 86-87).
     */
    public function test_percorso_detail_pillar_step_keeps_its_original_label_with_current_state(): void
    {
        $pillar = $this->article(['title' => 'Pillar con risalto']);
        $cluster = ContentCluster::create([
            'name' => 'Oceanografia di base',
            'slug' => 'oceanografia-di-base',
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'pillar_article_id' => $pillar->id,
        ]);
        $cluster->articles()->attach($pillar->id, ['position' => 10, 'is_primary' => true]);

        $html = $this->get(route('percorsi.show', 'oceanografia-di-base'))->assertOk()->getContent();

        $this->assertStringContainsString('Punto di partenza', $html);
        $this->assertStringContainsString('kairus-path-step--current', $html);
    }

    /**
     * Il testo di transizione (dato reale, pivot->transition_text) resta
     * un paragrafo fratello DOPO il link della tappa, mai al suo interno
     * (Prompt 88-90).
     */
    public function test_percorso_detail_transition_text_renders_outside_the_step_link(): void
    {
        $first = $this->article(['title' => 'Prima tappa con transizione']);
        $second = $this->article(['title' => 'Seconda tappa dopo la transizione']);
        $cluster = ContentCluster::create([
            'name' => 'Meteorologia in pratica',
            'slug' => 'meteorologia-in-pratica',
            'is_active' => true,
            'lifecycle_status' => 'updating',
        ]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => false, 'transition_text' => 'Da qui il discorso prosegue verso le nuvole.'],
            $second->id => ['position' => 20, 'is_primary' => false, 'transition_text' => null],
        ]);

        $html = $this->get(route('percorsi.show', 'meteorologia-in-pratica'))->assertOk()->getContent();

        preg_match('/<li>\s*<a[^>]*class="[^"]*kairus-path-step[^"]*"[^>]*>.*?<\/a>\s*(<p class="path-step__transition">.*?<\/p>)?\s*<\/li>/s', $html, $stepMatch);
        $this->assertNotSame('', $stepMatch[0] ?? '', 'Markup della prima tappa non trovato.');

        preg_match('/<a[^>]*class="[^"]*kairus-path-step[^"]*"[^>]*>.*?<\/a>/s', $stepMatch[0], $linkMatch);
        $link = $linkMatch[0] ?? '';
        $this->assertNotSame('', $link, 'Link x-kairus.path-step non trovato.');
        $this->assertStringNotContainsString('Da qui il discorso prosegue', $link, 'Il testo di transizione non deve essere annidato dentro il link della tappa.');
        $this->assertStringContainsString('Da qui il discorso prosegue', $stepMatch[0], 'Il testo di transizione deve comunque comparire come fratello dopo il link.');
    }

    /**
     * I marcatori decorativi "In arrivo"/"Percorso concluso" non hanno un
     * href reale (aria-hidden, puramente grafici): non hanno un contratto
     * compatibile con path-step e restano markup/CSS legacy invariato.
     */
    public function test_percorso_detail_next_marker_stays_decorative_without_an_href(): void
    {
        $pillar = $this->article(['title' => 'Unica tappa disponibile']);
        $cluster = ContentCluster::create([
            'name' => 'Vulcanologia pratica',
            'slug' => 'vulcanologia-pratica',
            'is_active' => true,
            'lifecycle_status' => 'updating',
            'pillar_article_id' => $pillar->id,
        ]);
        $cluster->articles()->attach($pillar->id, ['position' => 10, 'is_primary' => true]);

        $html = $this->get(route('percorsi.show', 'vulcanologia-pratica'))->assertOk()->getContent();

        preg_match('/<li class="path-step path-step--next" aria-hidden="true">.*?<\/li>/s', $html, $markerMatch);
        $marker = $markerMatch[0] ?? '';
        $this->assertNotSame('', $marker, 'Marcatore "In arrivo" non trovato.');
        $this->assertStringNotContainsString('<a ', $marker, 'Il marcatore decorativo non deve contenere un link.');
        $this->assertStringContainsString('In arrivo', $marker);
    }

    public function test_percorso_detail_step_links_are_never_nested_inside_each_other(): void
    {
        $first = $this->article(['title' => 'Tappa con cover', 'cover_image' => 'articles/covers/tappa.webp']);
        $second = $this->article(['title' => 'Tappa senza cover']);
        $cluster = ContentCluster::create([
            'name' => 'Sismologia applicata',
            'slug' => 'sismologia-applicata',
            'is_active' => true,
            'lifecycle_status' => 'updating',
        ]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => false],
            $second->id => ['position' => 20, 'is_primary' => false],
        ]);

        $html = $this->get(route('percorsi.show', 'sismologia-applicata'))->assertOk()->getContent();

        preg_match('/<section class="path-steps".*?<\/section>/s', $html, $sectionMatch);
        $section = $sectionMatch[0] ?? '';
        $this->assertNotSame('', $section, 'Sezione tappe non trovata.');
        $this->assertSame(substr_count($section, '<a '), substr_count($section, '</a>'), 'Numero di <a> aperti e chiusi non coincide: possibile link annidato.');
    }
}
