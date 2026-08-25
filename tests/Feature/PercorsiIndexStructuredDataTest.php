<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PercorsiIndexStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    private function collectionPageNodeFrom(string $html): array
    {
        preg_match_all(
            '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
            $html,
            $matches
        );

        foreach ($matches[1] ?? [] as $json) {
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach ($decoded['@graph'] ?? [$decoded] as $node) {
                if (($node['@type'] ?? null) === 'CollectionPage') {
                    return $node;
                }
            }
        }

        $this->fail('Nessun nodo @type=CollectionPage JSON-LD trovato su /percorsi.');
    }

    public function test_percorsi_index_exposes_collection_page_structured_data(): void
    {
        $html = $this->get(route('percorsi.index'))
            ->assertOk()
            ->getContent();

        $node = $this->collectionPageNodeFrom($html);

        $url = route('percorsi.index');

        $this->assertSame('CollectionPage', $node['@type']);
        $this->assertSame($url.'#collectionpage', $node['@id']);
        $this->assertSame($url, $node['url']);
        $this->assertSame('Percorsi', $node['name']);
        $this->assertSame(
            'Percorsi editoriali per esplorare i temi di Kairus in modo ordinato e progressivo.',
            $node['description']
        );
        $this->assertSame(
            url('/').'/#website',
            $node['isPartOf']['@id']
        );
    }

    public function test_percorsi_index_json_ld_is_valid_json(): void
    {
        $html = $this->get(route('percorsi.index'))
            ->assertOk()
            ->getContent();

        preg_match_all(
            '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is',
            $html,
            $matches
        );

        $this->assertNotEmpty($matches[1] ?? []);

        foreach ($matches[1] as $json) {
            json_decode($json, true);

            $this->assertSame(
                JSON_ERROR_NONE,
                json_last_error(),
                'JSON-LD non valido: '.json_last_error_msg()
            );
        }
    }

    public function test_percorsi_index_schema_does_not_invent_an_item_list(): void
    {
        $html = $this->get(route('percorsi.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('"@type": "ItemList"', $html);
        $this->assertStringNotContainsString('"@type":"ItemList"', $html);
    }
}
