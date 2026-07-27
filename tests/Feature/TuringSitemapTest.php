<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuringSitemapTest extends TestCase
{
    use RefreshDatabase;

    private const TURING_PATHS = [
        '/turing',
        '/turing/enigma',
        '/turing/computation',
        '/turing/intelligence',
        '/turing/ai',
        '/turing/legacy',
    ];

    public function test_sitemap_responds_with_the_correct_content_type(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_is_well_formed_xml(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertNotFalse($document, 'sitemap.xml non è XML valido.');
        $this->assertSame([], $errors, 'sitemap.xml contiene errori di parsing XML.');
    }

    public function test_sitemap_contains_all_six_turing_urls(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();
        $document = simplexml_load_string($xml);
        $locations = array_map('strval', $document->xpath('//*[local-name()="loc"]'));

        foreach (self::TURING_PATHS as $path) {
            $this->assertContains(url($path), $locations, "sitemap.xml non contiene {$path}.");
        }
    }

    public function test_sitemap_does_not_contain_duplicate_turing_urls(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();
        $document = simplexml_load_string($xml);
        $locations = array_map('strval', $document->xpath('//*[local-name()="loc"]'));

        foreach (self::TURING_PATHS as $path) {
            $occurrences = array_filter($locations, fn ($loc) => $loc === url($path));
            $this->assertCount(1, $occurrences, "sitemap.xml contiene {$path} più di una volta.");
        }
    }

    public function test_sitemap_does_not_contain_the_removed_duplicate_turing_ia_path(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/turing/ia<', $xml);
    }

    public function test_each_turing_sitemap_entry_has_a_valid_priority_and_changefreq(): void
    {
        $validFrequencies = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

        $xml = $this->get('/sitemap.xml')->getContent();
        $document = simplexml_load_string($xml);

        foreach ($document->url as $urlNode) {
            if (! in_array((string) $urlNode->loc, array_map('url', self::TURING_PATHS), true)) {
                continue;
            }

            $priority = (float) $urlNode->priority;
            $this->assertGreaterThanOrEqual(0.0, $priority);
            $this->assertLessThanOrEqual(1.0, $priority);
            $this->assertContains((string) $urlNode->changefreq, $validFrequencies);
        }
    }

    public function test_every_turing_page_listed_in_the_sitemap_is_actually_reachable(): void
    {
        foreach (self::TURING_PATHS as $path) {
            $this->get($path)->assertOk();
        }
    }
}
