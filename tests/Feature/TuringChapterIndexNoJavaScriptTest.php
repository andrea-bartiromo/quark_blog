<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 66 (programma "100 cantieri Kairus"). "Indice capitoli senza
 * JavaScript": verifica di regressione, non nuovo codice — dipendeva dal
 * Cantiere 58 perché entrambi toccano le stesse card dell'hub. Ispezione
 * diretta della sorgente (resources/views/turing/index.blade.php — non
 * il file inutilizzato resources/views/turing.blade.php, verificato non
 * referenziato da alcun controller — <x-special.feature-cards>,
 * x-turing.article.cta, ai.blade.php) ha confermato che l'intera rete di
 * navigazione fra capitoli è già costruita solo con veri tag `<a href>`
 * server-renderizzati — nessun tag `<button onclick>`, nessun contenuto
 * iniettato da JavaScript. PHPUnit non esegue mai JavaScript: ogni
 * asserzione qui è già di per sé una prova che quella parte di pagina
 * non dipende da JS.
 *
 * Nota: le 3 card dell'hub in <x-special.feature-cards> coprono solo
 * enigma/ai/legacy (dato reale di produzione, TuringSeeder/
 * TuringPageController::defaultRouteCards()). computation e intelligence
 * non sono in quella griglia, ma l'hub li collega comunque con veri
 * <a href> tramite i blocchi editoriali (turing.partials.editorial-blocks,
 * classe turing-editorial-link) — verificato con un dump diretto
 * dell'HTML reso. Sono inoltre raggiungibili dalle CTA "Continua il
 * percorso" di altri capitoli (flusso di lettura hub → Enigma →
 * Computation → Intelligence → AI → Legacy, vedi
 * Architettura_Editoriale_v1.0.docx §"Flusso di lettura").
 *
 * Le asserzioni cercano il path come SUFFISSO del valore dell'attributo
 * href (subito prima della virgoletta di chiusura), non come prefisso
 * subito dopo `href="`: gli href reali nel markup sono talvolta relativi
 * (le card dell'hub, i blocchi editoriali) e talvolta assoluti (route()
 * nelle CTA, es. http://localhost/turing/computation) — solo il
 * confronto sul suffisso combacia in entrambi i casi. Un confronto sul
 * prefisso (`href="` seguito subito dal path) mancherebbe ogni href
 * assoluto, anche se puntasse esattamente al capitolo cercato.
 */
class TuringChapterIndexNoJavaScriptTest extends TestCase
{
    use RefreshDatabase;

    private const CHAPTER_ROUTES = [
        'enigma' => ['turing.enigma', '/turing/enigma'],
        'ai' => ['turing.ai', '/turing/ai'],
        'legacy' => ['turing.legacy', '/turing/legacy'],
        'computation' => ['turing.computation', '/turing/computation'],
        'intelligence' => ['turing.intelligence', '/turing/intelligence'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['turing.chapters_public' => true]);
    }

    public function test_the_hub_feature_cards_are_real_anchor_tags_with_real_hrefs(): void
    {
        $html = $this->get(route('turing'))->assertOk()->getContent();

        // Le 3 card reali di produzione (TuringSeeder/defaultRouteCards()):
        // ciascuna deve comparire come <a href="..."> reale dentro
        // <x-special.feature-cards> (classe sp-feature-card--link), non un
        // <div>/<button> che richiederebbe JS per navigare.
        foreach (['enigma', 'ai', 'legacy'] as $chapter) {
            $path = self::CHAPTER_ROUTES[$chapter][1];

            $this->assertMatchesRegularExpression(
                '/<a[^>]*class="sp-feature-card sp-feature-card--link[^"]*"[^>]*href="[^"]*'.preg_quote($path, '/').'"/',
                $html,
                "card dell'hub verso '{$chapter}' non trovata come <a href> reale."
            );
        }
    }

    /**
     * Ogni capitolo reale deve essere raggiungibile con un vero
     * <a href="..."> da almeno un punto della rete (hub o un altro
     * capitolo) — mai solo tramite un meccanismo che richiede JavaScript
     * per funzionare.
     */
    public function test_every_real_chapter_is_reachable_by_a_real_anchor_tag_somewhere_in_the_network(): void
    {
        $pages = ['hub' => $this->get(route('turing'))->getContent()];

        foreach (self::CHAPTER_ROUTES as $chapter => [$routeName, $path]) {
            $pages[$chapter] = $this->get(route($routeName))->assertOk()->getContent();
        }

        foreach (self::CHAPTER_ROUTES as $targetChapter => [$targetRouteName, $targetPath]) {
            $hrefSuffixPattern = '/href="[^"]*'.preg_quote($targetPath, '/').'"/';

            $reachableFrom = collect($pages)
                ->filter(fn (string $html, string $fromPage) => $fromPage !== $targetChapter
                    && preg_match($hrefSuffixPattern, $html) === 1)
                ->keys();

            $this->assertNotEmpty(
                $reachableFrom,
                "nessuna pagina della rete contiene un <a href> reale verso {$targetPath}."
            );
        }
    }

    /**
     * Nessuna delle pagine capitolo deve delegare la navigazione fra
     * capitoli a un handler onclick — solo href reali.
     */
    public function test_no_chapter_navigation_link_relies_on_an_onclick_handler(): void
    {
        foreach (self::CHAPTER_ROUTES as $chapter => [$routeName, $path]) {
            $html = $this->get(route($routeName))->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '/<a[^>]+onclick=/',
                $html,
                "capitolo '{$chapter}': un link di navigazione usa onclick invece di un href reale."
            );
        }

        $hubHtml = $this->get(route('turing'))->getContent();
        $this->assertDoesNotMatchRegularExpression('/<a[^>]+onclick=/', $hubHtml);
    }

    /**
     * Il file resources/views/turing.blade.php (diverso dal reale
     * resources/views/turing/index.blade.php) non è referenziato da
     * alcun controller — tripwire contro un futuro collegamento
     * accidentale a un template morto e non più verificato da questi
     * test.
     */
    public function test_turing_page_controller_renders_the_real_index_view_not_the_unused_one(): void
    {
        $this->get(route('turing'))->assertViewIs('turing.index');
    }
}
