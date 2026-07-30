<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TuringEnigmaPageTest extends TestCase
{
    use RefreshDatabase;

    // Questi test esercitano /turing e i capitoli /turing/* assumendo
    // che siano pubblici (contenuto renderizzato, non un redirect):
    // stato futuro dietro config('turing.chapters_public'), attivato qui
    // esplicitamente. Il default di produzione (false, landing "In
    // arrivo" + redirect) e' coperto da TuringReleaseGateTest.
    protected function setUp(): void
    {
        parent::setUp();

        config(['turing.chapters_public' => true]);
    }

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

    public function test_enigma_page_covers_all_eleven_modules(): void
    {
        $response = $this->get(route('turing.enigma'));

        $response
            ->assertOk()
            ->assertSeeText('Una guerra combattuta anche con pattern e probabilità') // 2. apertura
            ->assertSeeText('Otto parti, una sola macchina') // 3. anatomia
            ->assertSeeText('Da un tasto premuto a una lampada accesa') // 4. percorso del segnale
            ->assertSeeText('Una chiave diversa ogni giorno') // 5. chiave giornaliera
            ->assertSeeText('Piccoli appigli linguistici') // 6. crib
            ->assertSeeText('Una catena di lavoro, non un atto solitario') // 7. pipeline Bletchley
            ->assertSeeText('L’automazione entra in gioco') // 8. Bombe
            ->assertSeeText('Enigma cifra, la Bombe restringe') // 9. confronto
            ->assertSeeText('Dall’invenzione alla cybersecurity di oggi') // 10. timeline
            ->assertSeeText('Dalla crittografia alla cybersecurity'); // 11. eredità
    }

    public function test_enigma_hero_includes_key_data_stats(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('enigma-stat-row', false)
            ->assertSeeText('Rotori intercambiabili')
            ->assertSeeText('Persone impiegate a Bletchley Park')
            ->assertSeeText('1939–1945');
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

    /**
     * @return array<string, array{0: string}>
     */
    public static function enigmaAssets(): array
    {
        return [
            'hero (default)' => ['hero-enigma.png'],
            'anatomia (default, base hotspot)' => ['cutaway-enigma.png'],
            'anatomia — componenti' => ['components.png'], // riferito solo nei dati delle feature-cards, non come <img> diretto
            'chiave giornaliera — impostazioni' => ['daily-key-settings.png'],
            'chiave giornaliera — plugboard' => ['plugboard.png'],
            'crib — operatore tedesco' => ['german-operator.png'],
            'confronto Enigma/Bombe' => ['comparison-enigma-bombe.png'],
            'timeline storica' => ['timeline-enigma.png'],
            'processo di cifratura (contenuto trascritto)' => ['encryption-process.png'], // non renderizzato come <img>, contenuto trascritto nello step-list del modulo 4
            // Asset non piu' referenziati direttamente dopo l'art-direction pass
            // con la libreria "editorial/" (restano su disco, non rimossi).
            'anatomia — rotori esplosi (superato da editorial/03)' => ['rotor-exploded.png'],
            'percorso del segnale (superato da editorial/04)' => ['signal-path.png'],
            'pipeline Bletchley — edificio (superato da editorial/11)' => ['bletchley-park.png'],
            'pipeline Bletchley — sala operativa (superato da editorial/05)' => ['operations-room.png'],
            'Bombe — diagramma tecnico (superato da editorial/09)' => ['bombe-machine.png'],
            'Bombe — Turing (superato da editorial/10)' => ['turing-bombe.png'],
            // Libreria editoriale in uso dopo l'art-direction pass.
            'editorial — apertura anatomia' => ['editorial/02_enigma-machine-anatomy.png'],
            'editorial — rotori esplosi' => ['editorial/03_rotor-exploded-view.png'],
            'editorial — percorso del segnale' => ['editorial/04_electrical-signal-path.png'],
            'editorial — sala operativa Bletchley' => ['editorial/05_bletchley-park-operations-room.png'],
            'editorial — esterno Hut 8' => ['editorial/11_hut-8-exterior.png'],
            'editorial — Bombe, tecnico al lavoro' => ['editorial/09_bombe-machine.png'],
            'editorial — Bombe, dettaglio tamburi' => ['editorial/10_bombe-detail.png'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('enigmaAssets')]
    public function test_enigma_image_asset_exists_on_disk(string $filename): void
    {
        $this->assertFileExists(public_path('images/turing/enigma/'.$filename));
    }

    public function test_enigma_hero_uses_the_default_asset_when_no_cms_override_exists(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('images/turing/enigma/hero-enigma.png', false);
    }

    public function test_enigma_hero_uses_the_cms_background_image_when_present(): void
    {
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'description' => 'Speciale editoriale dedicato ad Alan Turing.',
            'content' => [
                'editorial_blocks' => [
                    [
                        'enabled' => true,
                        'key' => 'enigma',
                        'title' => 'Titolo CMS per Enigma',
                        'background_image' => 'turing/enigma/turing-enigma-background.webp',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $response = $this->get(route('turing.enigma'));

        $response
            ->assertOk()
            ->assertSee('assets/img/turing/enigma/turing-enigma-background.webp', false)
            ->assertDontSee('images/turing/enigma/hero-enigma.png', false);
    }

    public function test_enigma_anatomy_figure_uses_the_default_asset_when_no_cms_override_exists(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('images/turing/enigma/cutaway-enigma.png', false)
            ->assertSeeText('Tastiera, lampboard, rotori, plugboard e riflettore');
    }

    public function test_enigma_anatomy_figure_uses_the_cms_image_when_present(): void
    {
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'description' => 'Speciale editoriale dedicato ad Alan Turing.',
            'content' => [
                'editorial_blocks' => [
                    [
                        'enabled' => true,
                        'key' => 'enigma',
                        'title' => 'Titolo CMS per Enigma',
                        'image' => 'turing/enigma/turing-enigma-panel.webp',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $response = $this->get(route('turing.enigma'));

        $response
            ->assertOk()
            ->assertSee('assets/img/turing/enigma/turing-enigma-panel.webp', false)
            ->assertDontSee('images/turing/enigma/cutaway-enigma.png', false)
            // Un'immagine CMS non ha una didascalia editoriale nota: la
            // figure non deve inventarne una per un asset arbitrario.
            ->assertDontSeeText('Tastiera, lampboard, rotori, plugboard e riflettore');
    }

    public function test_enigma_inline_images_have_descriptive_alt_text(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('alt="Fotografia ravvicinata dei rotori e della tastiera di una macchina Enigma aperta', false)
            ->assertSee('alt="Vista ravvicinata di quattro rotori Enigma allineati sul proprio asse', false)
            ->assertSee('alt="Visualizzazione scientifica del percorso del segnale elettrico attraverso i rotori', false)
            ->assertSee('alt="Illustrazione editoriale di un operatore militare tedesco che digita su una macchina Enigma"', false)
            ->assertSee('alt="Fotografia di un tecnico al lavoro davanti alla parete di tamburi rotanti della Bombe', false)
            ->assertSee('alt="Primo piano dei tamburi rotanti della Bombe', false)
            ->assertSee('alt="Fotografia di una sala operativa di Bletchley Park affollata di analisti al lavoro su documenti e schede', false);
    }

    public function test_enigma_hero_is_not_lazy_loaded(): void
    {
        // L'hero e' un background-image CSS (.enigma-hero), non un tag <img>:
        // gli attributi loading/fetchpriority non si applicano a un
        // background CSS, che il browser carica sempre in modo eager, senza
        // bisogno di alcun attributo. Verifichiamo quindi che l'hero non
        // passi in nessun caso per il meccanismo <img loading="lazy"> usato
        // dalle figure inline.
        $html = $this->get(route('turing.enigma'))->getContent();

        $heroStart = strpos($html, 'enigma-hero"');
        $nextSectionStart = strpos($html, 'id="enigma-apertura"', $heroStart);

        $this->assertNotFalse($heroStart);
        $this->assertNotFalse($nextSectionStart);

        $heroMarkup = substr($html, $heroStart, $nextSectionStart - $heroStart);

        $this->assertStringNotContainsString('<img', $heroMarkup);
        $this->assertStringContainsString('hero-enigma.png', $heroMarkup);
    }

    public function test_enigma_inline_figures_use_lazy_loading_and_async_decoding(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        // 10 figure inline (<x-turing.article.figure>, incluso il wrapper della
        // hotspot diagram che ne aggiunge una propria, e la nuova figura
        // fotografica di apertura in Anatomia): tutte lazy + async, mai eager.
        $this->assertSame(10, substr_count($html, 'class="turing-article-figure'));
        $this->assertGreaterThanOrEqual(10, substr_count($html, 'loading="lazy"'));
        $this->assertGreaterThanOrEqual(10, substr_count($html, 'decoding="async"'));
    }

    public function test_enigma_page_includes_the_technical_component_breakdown(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('sp-feature-cards', false)
            ->assertSeeText('Rotore')
            ->assertSeeText('Riflettore')
            ->assertSeeText('Plugboard')
            ->assertSeeText('Cavo stecker');
    }

    public function test_enigma_page_includes_the_signal_path_step_list(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('enigma-step-list', false)
            ->assertSeeText('Il tasto viene premuto')
            ->assertSeeText('Una lampada si accende');
    }

    public function test_enigma_page_includes_the_bletchley_pipeline_step_list(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSeeText('Intercettazione')
            ->assertSeeText('Verifica alla Bombe')
            ->assertSeeText('Traduzione e distribuzione');
    }

    public function test_enigma_page_includes_the_historical_timeline(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('sp-timeline', false)
            ->assertSeeText('1918')
            ->assertSeeText('Arthur Scherbius')
            ->assertSeeText('1939–1945')
            ->assertSeeText('1945 e oltre');
    }

    public function test_enigma_page_includes_the_enigma_vs_bombe_comparison_matrix(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('enigma-matrix', false)
            ->assertSeeText('Funzione')
            ->assertSeeText('Utente')
            ->assertSeeText('Input')
            ->assertSeeText('Output')
            ->assertSeeText('Scopo')
            ->assertSeeText('Effetto sullo spazio delle possibilità')
            ->assertSeeText('Ruolo operativo')
            ->assertSeeText('Restringe le chiavi possibili');
    }

    public function test_enigma_hero_image_is_preloaded(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        $this->assertMatchesRegularExpression(
            '#<link rel="preload" as="image" href="[^"]*hero-enigma\.png">#',
            $html
        );
    }

    public function test_enigma_page_includes_a_chapter_navigation(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        // Nav desktop (fissa) e mobile (orizzontale sticky): stessi anchor,
        // markup semplice <a href="#..."> funzionante anche senza JS.
        $this->assertSame(2, substr_count($html, 'data-enigma-chapter-nav>'));
        // Marcatore per il fade-out della nav oltre l'ultimo capitolo (vedi
        // public/js/turing-enigma.js): non deve coprire i contenuti di
        // chiusura.
        $this->assertStringContainsString('data-enigma-chapter-nav-end', $html);
        $this->assertStringContainsString('href="#enigma-anatomia"', $html);
        $this->assertStringContainsString('href="#enigma-segnale"', $html);
        $this->assertStringContainsString('href="#enigma-bletchley"', $html);
        $this->assertStringContainsString('aria-label="Capitoli della pagina"', $html);
    }

    public function test_enigma_page_anchors_have_scroll_margin_class(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        foreach (['enigma-apertura', 'enigma-anatomia', 'enigma-segnale', 'enigma-chiave', 'enigma-crib', 'enigma-bletchley', 'enigma-bombe', 'enigma-timeline'] as $id) {
            $this->assertMatchesRegularExpression(
                '#id="'.$id.'"[^>]*class="[^"]*enigma-anchor#',
                $html,
                "L'ancora #{$id} deve avere la classe enigma-anchor (scroll-margin-top)."
            );
        }
    }

    public function test_enigma_page_has_at_least_three_distinct_visual_surfaces(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        $surfaces = ['enigma-surface--paper', 'enigma-surface--blueprint', 'enigma-surface--signal', 'enigma-surface--operations', 'enigma-surface--dark'];
        $present = array_filter($surfaces, fn ($class) => str_contains($html, $class));

        $this->assertGreaterThanOrEqual(3, count($present));
        // Le tre sezioni esplicitamente richieste come ambientazione piu' immersiva.
        $this->assertStringContainsString('enigma-surface--signal', $html);
        $this->assertStringContainsString('enigma-surface--operations', $html);
    }

    public function test_enigma_signal_path_has_a_technical_diagram_trail(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('enigma-signal-trail', false)
            ->assertSee('enigma-step-list--signal', false)
            ->assertSee('enigma-step-list__item--pivot', false)
            ->assertSeeText('Riflessione');
    }

    public function test_enigma_final_mini_grid_has_real_informative_content(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('sp-feature-cards', false)
            ->assertSeeText('Crittografia')
            ->assertSeeText('Trasforma il messaggio rendendolo illeggibile senza la chiave corretta.')
            ->assertSeeText('Crittoanalisi')
            ->assertSeeText('Intelligence');
    }

    public function test_enigma_timeline_events_have_accessible_detail_modals(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        // 5 dei 7 eventi hanno 'details' (vedi Fase 14: "non rendere ogni
        // evento espandibile"), ciascuno con un trigger accessibile da
        // tastiera; l'implementazione di focus trap/ESC/aria-labelledby/
        // scroll lock è ereditata da <x-special.modal> (Decision #009),
        // già usata dalla Timeline della hub — non duplicata qui.
        $this->assertSame(5, substr_count($html, 'sp-timeline__details-trigger'));
        $this->assertSame(5, substr_count($html, 'aria-haspopup="dialog"'));
        $this->assertStringContainsString('Arthur Scherbius', $html);
        $this->assertStringContainsString('Marian Rejewski', $html);
        $this->assertStringContainsString('The Ultra Secret', $html);
    }

    public function test_enigma_timeline_final_event_covers_declassification(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSeeText('Il segreto, e poi la memoria pubblica');
    }

    public function test_enigma_page_respects_prefers_reduced_motion(): void
    {
        $this->assertStringContainsString(
            'prefers-reduced-motion',
            file_get_contents(public_path('css/turing-enigma.css'))
        );
    }

    public function test_enigma_enigma_js_is_present_and_progressive_enhancement_only(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        $this->assertStringContainsString('js/turing-enigma.js', $html);
        $this->assertFileExists(public_path('js/turing-enigma.js'));

        // La navigazione e' fatta di <a href="#..."> reali: deve restare
        // utilizzabile anche senza JavaScript (il file aggiunge solo
        // l'evidenziazione della sezione attiva).
        $js = file_get_contents(public_path('js/turing-enigma.js'));
        $this->assertStringContainsString('IntersectionObserver', $js);
    }

    public function test_enigma_anatomy_hotspot_diagram_has_eight_selectable_parts(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        // Hotspot CSS-only (radio + label): 8 input radio, 8 marcatori, 8
        // pannelli descrittivi — nessun JavaScript coinvolto.
        $this->assertSame(8, substr_count($html, 'enigma-hotspot__radio'));
        // 'enigma-hotspot__marker"' (con l'apice) esclude il wrapper
        // .enigma-hotspot__markers, di cui "marker" è altrimenti una sottostringa.
        $this->assertSame(8, substr_count($html, 'enigma-hotspot__marker"'));
        $this->assertSame(8, substr_count($html, 'enigma-hotspot__panel"'));
        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('Contatto a molla', $html);
        $this->assertStringContainsString('Cavo stecker', $html);
    }

    public function test_enigma_anatomy_hotspot_is_skipped_for_cms_override(): void
    {
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'description' => 'Speciale editoriale dedicato ad Alan Turing.',
            'content' => [
                'editorial_blocks' => [
                    [
                        'enabled' => true,
                        'key' => 'enigma',
                        'title' => 'Titolo CMS per Enigma',
                        'image' => 'turing/enigma/turing-enigma-panel.webp',
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $html = $this->get(route('turing.enigma'))->getContent();

        // Con un'immagine CMS non ha senso posizionare hotspot su
        // coordinate pensate per l'illustrazione di default: si torna alla
        // figure semplice.
        $this->assertStringNotContainsString('enigma-hotspot__radio', $html);
        $this->assertStringContainsString('turing-enigma-panel.webp', $html);
    }

    public function test_enigma_daily_key_has_an_operational_panel(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('enigma-key-panel', false)
            ->assertSeeText('DAILY KEY — 14 MAGGIO 1943')
            ->assertSeeText('Esempio illustrativo, non una configurazione storica documentata')
            ->assertSeeText('Ordine rotori')
            ->assertSeeText('II – IV – V')
            ->assertSeeText('Connessioni plugboard');
    }

    public function test_enigma_crib_has_an_investigative_table(): void
    {
        $this->get(route('turing.enigma'))
            ->assertOk()
            ->assertSee('enigma-crib-table', false)
            ->assertSeeText('Frammento cifrato')
            ->assertSeeText('Ipotesi (crib)')
            ->assertSeeText('non può mai cifrare una lettera in se stessa');
    }

    public function test_enigma_bombe_section_has_asymmetric_layout(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        $this->assertStringContainsString('enigma-bombe-grid', $html);
        $this->assertStringContainsString('enigma-bombe-grid__primary', $html);
        $this->assertStringContainsString('enigma-bombe-grid__secondary', $html);
        $this->assertStringContainsString('Dettaglio meccanico', $html);
    }

    public function test_enigma_closing_section_has_archive_elements_and_final_signal_trail(): void
    {
        $html = $this->get(route('turing.enigma'))->getContent();

        $this->assertStringContainsString('enigma-closing__stamp', $html);
        $this->assertStringContainsString('enigma-signal-trail--final', $html);
        $this->assertStringContainsString('Cybersecurity di oggi', $html);
    }
}
