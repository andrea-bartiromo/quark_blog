<?php

namespace Tests\Unit;

use App\Services\ArticleLinkInsertionService;
use Tests\TestCase;

class ArticleLinkInsertionServiceTest extends TestCase
{
    private ArticleLinkInsertionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ArticleLinkInsertionService;
    }

    // 1. Inserisce correttamente il link nel punto giusto, preservando il resto del testo
    public function test_it_inserts_a_link_around_the_anchor_text(): void
    {
        $body = '<p>Tra le soluzioni più diffuse ci sono i pannelli solari di nuova generazione, molto richiesti.</p>';

        $result = $this->service->insert($body, 'pannelli solari di nuova generazione', '/articolo/pannelli-solari');

        $this->assertNotNull($result);
        $this->assertStringContainsString('<a href="/articolo/pannelli-solari">pannelli solari di nuova generazione</a>', $result);
        $this->assertStringContainsString('Tra le soluzioni più diffuse ci sono i', $result);
        $this->assertStringContainsString(', molto richiesti.', $result);
    }

    // 2. Non inserisce mai dentro un link già esistente
    public function test_it_never_inserts_inside_an_existing_link(): void
    {
        $body = '<p>Leggi anche <a href="/articolo/altro">pannelli solari di nuova generazione</a> per approfondire.</p>';

        $result = $this->service->insert($body, 'pannelli solari di nuova generazione', '/articolo/pannelli-solari');

        $this->assertNull($result);
    }

    // 3. Non inserisce dentro un titolo (h1-h6)
    public function test_it_never_inserts_inside_a_heading(): void
    {
        $body = '<h2>Guida ai pannelli solari di nuova generazione</h2><p>Testo normale senza corrispondenze.</p>';

        $result = $this->service->insert($body, 'pannelli solari di nuova generazione', '/articolo/pannelli-solari');

        $this->assertNull($result);
    }

    // 4. Non inserisce dentro una citazione (blockquote)
    public function test_it_never_inserts_inside_a_blockquote(): void
    {
        $body = '<blockquote>I pannelli solari di nuova generazione cambieranno tutto.</blockquote><p>Altro testo.</p>';

        $result = $this->service->insert($body, 'pannelli solari di nuova generazione', '/articolo/pannelli-solari');

        $this->assertNull($result);
    }

    // 5. Se la frase non è più presente nel body corrente, fallisce esplicitamente (nessuna modifica)
    public function test_it_returns_null_when_the_anchor_phrase_is_no_longer_present(): void
    {
        $body = '<p>Il testo è stato riscritto e non contiene più quella frase.</p>';

        $result = $this->service->insert($body, 'pannelli solari di nuova generazione', '/articolo/pannelli-solari');

        $this->assertNull($result);
    }

    // 6. Se la prima occorrenza è in un contesto vietato ma ne esiste una valida altrove, la trova
    public function test_it_skips_a_forbidden_occurrence_and_uses_a_valid_one_elsewhere(): void
    {
        $body = '<h2>Pannelli solari di nuova generazione: la guida</h2>'.
            '<p>Abbiamo parlato spesso di pannelli solari di nuova generazione in altri articoli.</p>';

        $result = $this->service->insert($body, 'pannelli solari di nuova generazione', '/articolo/pannelli-solari');

        $this->assertNotNull($result);
        $this->assertStringContainsString('<h2>Pannelli solari di nuova generazione: la guida</h2>', $result);
        $this->assertStringContainsString('<a href="/articolo/pannelli-solari">pannelli solari di nuova generazione</a>', $result);
    }

    // 7. Il testo dell'anchor viene comunque escapato correttamente dalle API DOM
    public function test_it_safely_escapes_the_anchor_text_via_dom_apis(): void
    {
        $body = '<p>Parliamo di energia & sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia & sviluppo sostenibile', '/articolo/energia-sviluppo');

        $this->assertNotNull($result);
        $this->assertMatchesRegularExpression('/<a href="[^"]*">energia &amp; sviluppo sostenibile<\/a>/', $result);
        $this->assertStringContainsString('energia &amp; sviluppo sostenibile', $result);
    }

    // 8. Corpo vuoto o anchor vuota: nessun inserimento
    public function test_it_returns_null_for_empty_body_or_anchor(): void
    {
        $this->assertNull($this->service->insert('', 'termine', '/articolo/x'));
        $this->assertNull($this->service->insert('<p>Testo.</p>', '', '/articolo/x'));
        $this->assertNull($this->service->insert('<p>Testo.</p>', '   ', '/articolo/x'));
    }

    // 9. Un href con un apice doppio (tentativo onmouseover) viene rifiutato del tutto, non "solo escapato"
    //    — l'inserimento non deve mai dipendere dal comportamento di escaping del serializzatore DOM
    //    sottostante, che si è verificato differire tra build di libxml2 (vedi docblock di classe).
    public function test_it_rejects_a_href_containing_a_double_quote(): void
    {
        $body = '<p>Parliamo di energia e sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia e sviluppo sostenibile', '/articolo/test?a=1&b=2"onmouseover="alert(1)');

        $this->assertNull($result);
    }

    // 10. Un href con schema javascript: viene rifiutato
    public function test_it_rejects_a_javascript_scheme_href(): void
    {
        $body = '<p>Parliamo di energia e sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia e sviluppo sostenibile', 'javascript:alert(1)');

        $this->assertNull($result);
    }

    // 11. Un href con schema data: viene rifiutato
    public function test_it_rejects_a_data_scheme_href(): void
    {
        $body = '<p>Parliamo di energia e sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia e sviluppo sostenibile', 'data:text/html,<script>alert(1)</script>');

        $this->assertNull($result);
    }

    // 12. Un URL relativo interno valido viene accettato e inserito correttamente
    public function test_it_accepts_a_valid_relative_internal_url(): void
    {
        $body = '<p>Parliamo di energia e sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia e sviluppo sostenibile', '/articolo/energia-sviluppo-sostenibile');

        $this->assertNotNull($result);
        $this->assertStringContainsString('<a href="/articolo/energia-sviluppo-sostenibile">energia e sviluppo sostenibile</a>', $result);
    }

    // 13. Una query string lecita con "&" viene accettata (non va confusa con un tentativo di injection)
    public function test_it_accepts_a_legitimate_query_string_with_ampersand(): void
    {
        $body = '<p>Parliamo di energia e sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia e sviluppo sostenibile', '/articolo/x?ref=home&utm_source=newsletter');

        $this->assertNotNull($result);
        $this->assertStringContainsString('href="/articolo/x?ref=home&amp;utm_source=newsletter"', $result);
    }

    // 14. Un URL assoluto sul proprio dominio (come route()) viene accettato; un dominio esterno viene rifiutato
    public function test_it_accepts_own_host_absolute_urls_and_rejects_external_hosts(): void
    {
        $body = '<p>Parliamo di energia e sviluppo sostenibile in Italia.</p>';
        $ownHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        $accepted = $this->service->insert($body, 'energia e sviluppo sostenibile', 'http://'.$ownHost.'/articolo/x');
        $this->assertNotNull($accepted);

        $rejected = $this->service->insert($body, 'energia e sviluppo sostenibile', 'http://evil-external-host.example/articolo/x');
        $this->assertNull($rejected);
    }

    // 15. Un URL protocol-relative ("//host/...") viene rifiutato: potrebbe puntare a un altro dominio
    public function test_it_rejects_a_protocol_relative_url(): void
    {
        $body = '<p>Parliamo di energia e sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia e sviluppo sostenibile', '//evil-external-host.example/articolo/x');

        $this->assertNull($result);
    }
}
