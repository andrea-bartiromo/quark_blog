<?php

namespace Tests\Unit;

use App\Services\ArticlePrimarySourcesParser;
use Tests\TestCase;

class ArticlePrimarySourcesParserTest extends TestCase
{
    private function parser(): ArticlePrimarySourcesParser
    {
        return new ArticlePrimarySourcesParser;
    }

    public function test_null_and_blank_input_produce_no_items(): void
    {
        $this->assertSame([], $this->parser()->parse(null));
        $this->assertSame([], $this->parser()->parse(''));
        $this->assertSame([], $this->parser()->parse("   \n\n   "));
    }

    public function test_a_line_that_is_only_a_url_becomes_a_link(): void
    {
        $items = $this->parser()->parse('https://www.nature.com/articles/12345');

        $this->assertSame([
            ['type' => 'link', 'text' => 'https://www.nature.com/articles/12345', 'url' => 'https://www.nature.com/articles/12345'],
        ], $items);
    }

    public function test_plain_descriptive_text_becomes_text_not_a_link(): void
    {
        $items = $this->parser()->parse('Comunicato stampa ESA, ottobre 2026');

        $this->assertSame('text', $items[0]['type']);
        $this->assertNull($items[0]['url']);
    }

    public function test_text_mixed_with_a_url_is_not_promoted_to_a_link(): void
    {
        $items = $this->parser()->parse('Fonte: https://example.com/paper');

        $this->assertSame('text', $items[0]['type']);
        $this->assertSame('Fonte: https://example.com/paper', $items[0]['text']);
    }

    public function test_multiple_lines_are_all_preserved_in_order(): void
    {
        $raw = "https://example.com/a\nTesto libero\nhttps://example.com/b";

        $items = $this->parser()->parse($raw);

        $this->assertCount(3, $items);
        $this->assertSame('link', $items[0]['type']);
        $this->assertSame('text', $items[1]['type']);
        $this->assertSame('link', $items[2]['type']);
    }

    public function test_empty_lines_between_entries_are_skipped_without_losing_content(): void
    {
        $raw = "https://example.com/a\n\n\nhttps://example.com/b\n   \n";

        $items = $this->parser()->parse($raw);

        $this->assertCount(2, $items);
    }

    public function test_unsafe_url_schemes_are_never_classified_as_links(): void
    {
        $unsafe = [
            'javascript:alert(1)',
            'data:text/html,<script>alert(1)</script>',
            'vbscript:msgbox(1)',
        ];

        foreach ($unsafe as $line) {
            $items = $this->parser()->parse($line);
            $this->assertSame('text', $items[0]['type'], "Expected '{$line}' to be classified as text");
            $this->assertNull($items[0]['url']);
        }
    }

    public function test_hostile_markup_is_preserved_as_plain_text_content_never_dropped(): void
    {
        $raw = '<script>alert(1)</script> testo dopo il tag';

        $items = $this->parser()->parse($raw);

        $this->assertCount(1, $items);
        $this->assertSame('text', $items[0]['type']);
        $this->assertSame($raw, $items[0]['text']);
    }

    public function test_unicode_text_is_preserved_verbatim(): void
    {
        $raw = 'Università degli Studi — ricerca sui raggi cosmici (© 2026)';

        $items = $this->parser()->parse($raw);

        $this->assertSame($raw, $items[0]['text']);
    }

    public function test_ftp_and_relative_urls_are_not_treated_as_links(): void
    {
        $this->assertSame('text', $this->parser()->parse('ftp://example.com/file')[0]['type']);
        $this->assertSame('text', $this->parser()->parse('/percorso/relativo')[0]['type']);
        $this->assertSame('text', $this->parser()->parse('www.example.com')[0]['type']);
    }

    public function test_a_bare_doi_line_becomes_a_doi_org_link(): void
    {
        $items = $this->parser()->parse('10.1038/s41586-026-00001-2');

        $this->assertSame('link', $items[0]['type']);
        $this->assertSame('https://doi.org/10.1038/s41586-026-00001-2', $items[0]['url']);
        $this->assertSame('10.1038/s41586-026-00001-2', $items[0]['text']);
    }

    public function test_a_doi_org_url_line_becomes_a_normalized_doi_link(): void
    {
        $items = $this->parser()->parse('https://dx.doi.org/10.1038/s41586-026-00001-2');

        $this->assertSame('link', $items[0]['type']);
        $this->assertSame('https://doi.org/10.1038/s41586-026-00001-2', $items[0]['url']);
    }

    public function test_a_doi_embedded_in_descriptive_text_is_not_promoted_to_a_link(): void
    {
        $items = $this->parser()->parse('Studio principale — 10.1038/s41586-026-00001-2');

        $this->assertSame('text', $items[0]['type']);
        $this->assertNull($items[0]['url']);
    }
}
