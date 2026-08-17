<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Copre il primo intervento dell'audit dati strutturati (Fase 3):
 * Organization + WebSite in un unico blocco @graph, solo sulla homepage.
 * Article/NewsArticle, BreadcrumbList, Person e CollectionPage arriveranno
 * in commit separati — non testati qui.
 *
 * Tutte le asserzioni decodificano il JSON-LD ed esaminano la struttura
 * risultante, non il markup: restano valide indipendentemente da
 * indentazione/formattazione del blocco <script>.
 */
class HomeStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function homeStructuredData(): array
    {
        $html = $this->get(route('home'))->getContent();

        $this->assertMatchesRegularExpression(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $html,
            'Nessun blocco <script type="application/ld+json"> trovato sulla homepage.'
        );

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        $decoded = json_decode($matches[1], true);
        $this->assertIsArray($decoded, 'Il blocco JSON-LD non è JSON valido: '.json_last_error_msg());

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeOfType(array $graph, string $type): array
    {
        $node = collect($graph)->first(fn ($item) => ($item['@type'] ?? null) === $type);

        $this->assertIsArray($node, "Nessun nodo @type={$type} trovato nel @graph.");

        return $node;
    }

    public function test_home_page_includes_a_valid_json_ld_graph(): void
    {
        $data = $this->homeStructuredData();

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertIsArray($data['@graph']);
        $this->assertCount(2, $data['@graph']);
    }

    public function test_organization_node_has_stable_id_and_real_verified_data(): void
    {
        $organization = $this->nodeOfType($this->homeStructuredData()['@graph'], 'Organization');

        $this->assertSame(url('/').'/#organization', $organization['@id']);
        $this->assertSame(config('laboratorio.name'), $organization['name']);
        $this->assertSame(url('/'), $organization['url']);

        // Nessun dato non verificato: niente sameAs/address/telephone/legalName.
        $this->assertArrayNotHasKey('sameAs', $organization);
        $this->assertArrayNotHasKey('address', $organization);
        $this->assertArrayNotHasKey('telephone', $organization);
        $this->assertArrayNotHasKey('legalName', $organization);
    }

    public function test_organization_logo_is_an_absolute_reachable_raster_image(): void
    {
        $organization = $this->nodeOfType($this->homeStructuredData()['@graph'], 'Organization');

        $this->assertSame('ImageObject', $organization['logo']['@type']);
        $this->assertSame(asset('assets/icons/icon-512.png'), $organization['logo']['url']);
        $this->assertSame(512, $organization['logo']['width']);
        $this->assertSame(512, $organization['logo']['height']);
        $this->assertStringEndsNotWith('.svg', $organization['logo']['url']);

        // L'asset referenziato esiste davvero e non è una SVG (Google non
        // accetta SVG come formato per Organization.logo).
        $this->assertFileExists(public_path('assets/icons/icon-512.png'));
        $this->assertStringStartsWith(
            "\x89PNG",
            file_get_contents(public_path('assets/icons/icon-512.png'))
        );
    }

    public function test_website_node_has_stable_id_url_name_description_and_publisher_reference(): void
    {
        $website = $this->nodeOfType($this->homeStructuredData()['@graph'], 'WebSite');

        $this->assertSame(url('/').'/#website', $website['@id']);
        $this->assertSame(url('/'), $website['url']);
        $this->assertSame(config('laboratorio.name'), $website['name']);
        $this->assertSame(config('laboratorio.description'), $website['description']);

        // Riferimento puro all'Organization tramite @id, non un duplicato.
        $this->assertSame(url('/').'/#organization', $website['publisher']['@id']);
        $this->assertCount(1, $website['publisher']);
    }

    public function test_website_node_does_not_declare_a_search_action(): void
    {
        $website = $this->nodeOfType($this->homeStructuredData()['@graph'], 'WebSite');

        $this->assertArrayNotHasKey('potentialAction', $website);
    }

    public function test_json_ld_cannot_be_broken_out_of_by_a_site_name_containing_a_closing_script_tag(): void
    {
        config(['laboratorio.name' => 'Kairus </script><script>alert(1)</script>']);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)</script>', $html);
    }

    public function test_organization_and_website_only_appear_on_the_home_page(): void
    {
        $this->get(route('home'))->assertOk()->assertSee('application/ld+json', false);

        // /notizie ha il proprio blocco CollectionPage (Fase 5), non Organization/WebSite.
        $notizieHtml = $this->get(route('notizie'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Organization', $notizieHtml);
        $this->assertStringNotContainsString('"WebSite"', $notizieHtml);

        $this->get('/turing')->assertOk()->assertDontSee('application/ld+json', false);
    }
}
