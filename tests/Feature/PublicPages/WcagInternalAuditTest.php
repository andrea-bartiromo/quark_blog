<?php

namespace Tests\Feature\PublicPages;

use App\Services\PublicPages\InProcessPageFetcher;
use App\Services\PublicPages\PublicPageInventory;
use App\Services\PublicPages\WcagInternalAudit;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Cantiere 29 (programma 100-cantieri Kairus). WcagInternalAudit itera
 * App\Services\PublicPages\PublicPageInventory (Cantiere 21) e analizza
 * il markup HTML restituito da App\Services\PublicPages\InProcessPageFetcher
 * per i criteri WCAG statici (lang, heading, landmark, skip-link, alt,
 * nome accessibile). Ogni test finge sia l'inventario (una sola pagina
 * fittizia) sia il fetcher (HTML/stato controllati), cosi' da isolare
 * completamente la logica di analisi dalle vere rotte/dati — nessuna
 * dipendenza da un articolo o una categoria reale.
 */
class WcagInternalAuditTest extends TestCase
{
    private const COMPLIANT_HTML = <<<'HTML'
        <!DOCTYPE html>
        <html lang="it">
        <head><title>Prova</title></head>
        <body>
        <a href="#main-content" class="skip-link">Vai al contenuto principale</a>
        <header>Intestazione</header>
        <main id="main-content">
        <h1>Titolo pagina</h1>
        <h2>Sezione</h2>
        <img src="/x.jpg" alt="Descrizione">
        <img src="/y.jpg" alt="">
        <a href="/altro">Continua a leggere</a>
        <button aria-label="Chiudi">×</button>
        </main>
        <footer>Piè di pagina</footer>
        </body>
        </html>
        HTML;

    private function fakeInventory(?string $sampleUrl): void
    {
        $inventory = new class($sampleUrl) extends PublicPageInventory
        {
            public function __construct(private readonly ?string $url) {}

            public function pages(): array
            {
                return [['key' => 'prova', 'label' => 'Prova', 'route_name' => 'prova', 'kind' => 'static', 'sample_url' => $this->url]];
            }
        };

        $this->app->instance(PublicPageInventory::class, $inventory);
    }

    private function fakeFetcher(string $html, int $status = 200): void
    {
        $fake = new class($html, $status) extends InProcessPageFetcher
        {
            public function __construct(private readonly string $html, private readonly int $status) {}

            public function fetch(string $url): Response
            {
                return new Response($this->html, $this->status);
            }
        };

        $this->app->instance(InProcessPageFetcher::class, $fake);
    }

    public function test_a_fully_compliant_page_has_no_findings(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(self::COMPLIANT_HTML);

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertSame([], $results[0]['findings']);
        $this->assertSame(200, $results[0]['http_status']);
    }

    public function test_a_page_without_a_sample_url_has_no_findings_and_a_null_status(): void
    {
        $this->fakeInventory(null);
        $this->fakeFetcher(self::COMPLIANT_HTML);

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertNull($results[0]['url']);
        $this->assertNull($results[0]['http_status']);
        $this->assertSame([], $results[0]['findings']);
    }

    public function test_a_non_200_status_is_reported_and_skips_the_markup_checks(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher('errore', 500);

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertCount(1, $results[0]['findings']);
        $this->assertStringContainsString('500', $results[0]['findings'][0]);
    }

    public function test_missing_lang_attribute_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<html lang="it">', '<html>', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('Attributo lang assente su <html> (WCAG 3.1.1).', $results[0]['findings']);
    }

    public function test_missing_h1_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<h1>Titolo pagina</h1>', '', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('Nessun <h1> in pagina (WCAG 1.3.1/2.4.6).', $results[0]['findings']);
    }

    public function test_multiple_h1_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<h1>Titolo pagina</h1>', '<h1>Titolo pagina</h1><h1>Un altro</h1>', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('Più di un <h1> in pagina (2) (WCAG 1.3.1).', $results[0]['findings']);
    }

    public function test_a_heading_level_jump_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<h2>Sezione</h2>', '<h4>Sezione</h4>', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('Salto di livello heading da h1 a h4 (WCAG 1.3.1/2.4.6).', $results[0]['findings']);
    }

    public function test_a_missing_landmark_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<header>Intestazione</header>', '', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('Landmark <header> assente (WCAG 1.3.1).', $results[0]['findings']);
    }

    public function test_a_missing_skip_link_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<a href="#main-content" class="skip-link">Vai al contenuto principale</a>', '', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('Skip-link assente (WCAG 2.4.1).', $results[0]['findings']);
    }

    public function test_a_skip_link_pointing_to_a_missing_id_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('href="#main-content"', 'href="#non-esiste"', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('Skip-link punta a un id inesistente: #non-esiste (WCAG 2.4.1).', $results[0]['findings']);
    }

    public function test_an_image_missing_the_alt_attribute_entirely_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<img src="/x.jpg" alt="Descrizione">', '<img src="/x.jpg">', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('1 <img> senza attributo alt (WCAG 1.1.1).', $results[0]['findings']);
    }

    public function test_an_empty_alt_attribute_is_not_a_finding(): void
    {
        // alt="" e' il modo corretto di marcare un'immagine puramente
        // decorativa (gia' verificato manualmente in Cantiere I): non e'
        // un finding, solo l'attributo del tutto assente lo e'.
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(self::COMPLIANT_HTML);

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertSame([], $results[0]['findings']);
    }

    public function test_a_link_without_an_accessible_name_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace('<a href="/altro">Continua a leggere</a>', '<a href="/altro"></a>', self::COMPLIANT_HTML));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('1 link/pulsante senza nome accessibile (nessun testo, aria-label o aria-labelledby) (WCAG 2.4.4/4.1.2).', $results[0]['findings']);
    }

    public function test_a_button_with_only_an_aria_label_is_not_a_finding(): void
    {
        // Il pulsante ×  del markup di prova non ha testo utile ma ha
        // aria-label="Chiudi": deve restare senza finding.
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(self::COMPLIANT_HTML);

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertSame([], $results[0]['findings']);
    }

    /**
     * Codex (PR #577, P2): la sola presenza dell'attributo aria-labelledby
     * non basta — se punta a un id inesistente (o vuoto) il controllo
     * resta comunque senza nome accessibile.
     */
    public function test_a_link_with_an_aria_labelledby_pointing_to_a_missing_id_is_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace(
            '<a href="/altro">Continua a leggere</a>',
            '<a href="/altro" aria-labelledby="non-esiste"></a>',
            self::COMPLIANT_HTML
        ));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertContains('1 link/pulsante senza nome accessibile (nessun testo, aria-label o aria-labelledby) (WCAG 2.4.4/4.1.2).', $results[0]['findings']);
    }

    public function test_a_link_with_an_aria_labelledby_pointing_to_a_real_id_is_not_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace(
            '<a href="/altro">Continua a leggere</a>',
            '<span id="etichetta-altro">Approfondisci</span><a href="/altro" aria-labelledby="etichetta-altro"></a>',
            self::COMPLIANT_HTML
        ));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertSame([], $results[0]['findings']);
    }

    /**
     * Codex (PR #577, P2): textContent non include gli alt dei
     * discendenti — un link solo-immagine con un alt descrittivo ha
     * comunque un nome accessibile reale, non deve risultare un finding.
     */
    public function test_an_image_only_link_with_a_descriptive_alt_is_not_a_finding(): void
    {
        $this->fakeInventory('http://example.test/prova');
        $this->fakeFetcher(str_replace(
            '<a href="/altro">Continua a leggere</a>',
            '<a href="/altro"><img src="/icona.svg" alt="Continua a leggere"></a>',
            self::COMPLIANT_HTML
        ));

        $results = app(WcagInternalAudit::class)->audit();

        $this->assertSame([], $results[0]['findings']);
    }
}
