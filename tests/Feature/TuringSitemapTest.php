<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuringSitemapTest extends TestCase
{
    use RefreshDatabase;

    private const TURING_CHAPTER_PATHS = [
        '/turing/enigma',
        '/turing/computation',
        '/turing/intelligence',
        '/turing/ai',
        '/turing/legacy',
    ];

    private const ALL_TURING_PATHS = [
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

    public function test_sitemap_does_not_contain_the_removed_duplicate_turing_ia_path(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/turing/ia<', $xml);
    }

    /*
     * Stato di default (config('turing.chapters_public') = false, come in
     * produzione prima del rilascio): /turing resta nella sitemap, i
     * capitoli no, perché reindirizzano a /turing e non hanno contenuti
     * propri da indicizzare.
     */
    public function test_sitemap_contains_turing_but_not_the_unpublished_chapters(): void
    {
        $this->assertFalse((bool) config('turing.chapters_public'));

        $xml = $this->get('/sitemap.xml')->getContent();
        $document = simplexml_load_string($xml);
        $locations = array_map('strval', $document->xpath('//*[local-name()="loc"]'));

        $this->assertContains(url('/turing'), $locations, 'sitemap.xml deve contenere /turing.');

        foreach (self::TURING_CHAPTER_PATHS as $path) {
            $this->assertNotContains(url($path), $locations, "sitemap.xml non deve contenere {$path} finché non è pubblico.");
        }
    }

    public function test_unpublished_chapters_are_not_reachable_even_though_removed_from_the_sitemap(): void
    {
        foreach (self::TURING_CHAPTER_PATHS as $path) {
            $this->get($path)->assertRedirect(route('turing'));
        }

        $this->get('/turing')->assertOk();
    }

    /*
     * Stato futuro (config('turing.chapters_public') = true, al rilascio
     * completo dello Speciale): tutte e sei le URL tornano nella sitemap,
     * senza duplicati, ciascuna raggiungibile e con priority/changefreq
     * validi — stesso comportamento della sitemap prima di questo rilascio
     * "in arrivo".
     */
    public function test_sitemap_contains_all_six_turing_urls_once_chapters_are_public(): void
    {
        config(['turing.chapters_public' => true]);

        $xml = $this->get('/sitemap.xml')->getContent();
        $document = simplexml_load_string($xml);
        $locations = array_map('strval', $document->xpath('//*[local-name()="loc"]'));

        foreach (self::ALL_TURING_PATHS as $path) {
            $this->assertContains(url($path), $locations, "sitemap.xml non contiene {$path}.");
        }
    }

    public function test_sitemap_does_not_contain_duplicate_turing_urls_once_chapters_are_public(): void
    {
        config(['turing.chapters_public' => true]);

        $xml = $this->get('/sitemap.xml')->getContent();
        $document = simplexml_load_string($xml);
        $locations = array_map('strval', $document->xpath('//*[local-name()="loc"]'));

        foreach (self::ALL_TURING_PATHS as $path) {
            $occurrences = array_filter($locations, fn ($loc) => $loc === url($path));
            $this->assertCount(1, $occurrences, "sitemap.xml contiene {$path} più di una volta.");
        }
    }

    public function test_each_turing_sitemap_entry_has_a_valid_priority_and_changefreq_once_chapters_are_public(): void
    {
        config(['turing.chapters_public' => true]);

        $validFrequencies = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

        $xml = $this->get('/sitemap.xml')->getContent();
        $document = simplexml_load_string($xml);

        foreach ($document->url as $urlNode) {
            if (! in_array((string) $urlNode->loc, array_map('url', self::ALL_TURING_PATHS), true)) {
                continue;
            }

            $priority = (float) $urlNode->priority;
            $this->assertGreaterThanOrEqual(0.0, $priority);
            $this->assertLessThanOrEqual(1.0, $priority);
            $this->assertContains((string) $urlNode->changefreq, $validFrequencies);
        }
    }

    public function test_every_turing_page_listed_in_the_sitemap_is_actually_reachable_once_chapters_are_public(): void
    {
        config(['turing.chapters_public' => true]);

        foreach (self::ALL_TURING_PATHS as $path) {
            $this->get($path)->assertOk();
        }
    }
}
