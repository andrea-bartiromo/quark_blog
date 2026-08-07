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

    // 7. href e testo vengono sempre escapati correttamente dalle API DOM (nessuna injection possibile)
    public function test_it_safely_escapes_special_characters_via_dom_apis(): void
    {
        $body = '<p>Parliamo di energia & sviluppo sostenibile in Italia.</p>';

        $result = $this->service->insert($body, 'energia & sviluppo sostenibile', '/articolo/test?a=1&b=2"onmouseover="alert(1)');

        $this->assertNotNull($result);
        // Il carattere " nell'URL viene neutralizzato dalla serializzazione DOM
        // (percent-encoded), quindi non può mai chiudere l'attributo href e
        // iniettare un nuovo attributo (es. onmouseover).
        $this->assertStringNotContainsString('onmouseover="alert', $result);
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
}
