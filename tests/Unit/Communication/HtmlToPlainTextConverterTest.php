<?php

namespace Tests\Unit\Communication;

use App\Services\Communication\HtmlToPlainTextConverter;
use PHPUnit\Framework\TestCase;

class HtmlToPlainTextConverterTest extends TestCase
{
    private function convert(string $html): string
    {
        return (new HtmlToPlainTextConverter)->convert($html);
    }

    public function test_empty_input_returns_empty_string(): void
    {
        $this->assertSame('', $this->convert(''));
        $this->assertSame('', $this->convert('   '));
    }

    public function test_plain_text_with_no_tags_passes_through(): void
    {
        $this->assertSame('Testo semplice senza tag.', $this->convert('Testo semplice senza tag.'));
    }

    public function test_headings_are_separated_by_blank_lines(): void
    {
        $text = $this->convert('<h1>Titolo</h1><p>Corpo.</p>');

        $this->assertSame("Titolo\n\nCorpo.", $text);
    }

    public function test_multiple_paragraphs_are_separated_by_a_blank_line(): void
    {
        $text = $this->convert('<p>Primo paragrafo.</p><p>Secondo paragrafo.</p>');

        $this->assertSame("Primo paragrafo.\n\nSecondo paragrafo.", $text);
    }

    public function test_links_render_as_text_and_url(): void
    {
        $text = $this->convert('<p>Leggi <a href="https://kairus.it/articolo/1">questo articolo</a>.</p>');

        $this->assertStringContainsString('questo articolo (https://kairus.it/articolo/1)', $text);
    }

    public function test_bare_url_link_renders_once_not_duplicated(): void
    {
        $text = $this->convert('<a href="https://kairus.it">https://kairus.it</a>');

        $this->assertSame('https://kairus.it', $text);
    }

    public function test_lists_render_with_dash_prefix_one_item_per_line(): void
    {
        $text = $this->convert('<ul><li>Primo</li><li>Secondo</li><li>Terzo</li></ul>');

        $this->assertSame("- Primo\n\n- Secondo\n\n- Terzo", $text);
    }

    public function test_unicode_and_emoji_are_preserved(): void
    {
        $text = $this->convert('<p>Città, caffè, perché 🔬🚀</p>');

        $this->assertSame('Città, caffè, perché 🔬🚀', $text);
    }

    public function test_html_entities_are_decoded(): void
    {
        $text = $this->convert('<p>Tom &amp; Jerry &mdash; &ldquo;cita&rdquo;</p>');

        $this->assertStringContainsString('Tom & Jerry', $text);
    }

    public function test_malformed_html_never_throws_and_produces_readable_output(): void
    {
        $text = $this->convert('<p>Paragrafo non chiuso <b>grassetto <i>corsivo</p>');

        $this->assertStringContainsString('Paragrafo non chiuso', $text);
        $this->assertStringContainsString('grassetto', $text);
        $this->assertStringContainsString('corsivo', $text);
    }

    public function test_script_and_style_content_is_never_included(): void
    {
        $text = $this->convert('<p>Visibile</p><script>alert(1)</script><style>.x{color:red}</style>');

        $this->assertSame('Visibile', $text);
        $this->assertStringNotContainsString('alert', $text);
        $this->assertStringNotContainsString('color:red', $text);
    }

    public function test_br_forces_a_line_break_within_a_block(): void
    {
        $text = $this->convert('<p>Prima riga<br>Seconda riga</p>');

        $this->assertSame("Prima riga\n\nSeconda riga", $text);
    }

    public function test_whitespace_only_source_collapses_to_empty(): void
    {
        $this->assertSame('', $this->convert("<p>   </p><div>\n\n</div>"));
    }

    public function test_nested_blocks_do_not_produce_excessive_blank_lines(): void
    {
        $text = $this->convert('<div><p>Uno</p><p>Due</p></div>');

        $this->assertSame("Uno\n\nDue", $text);
    }
}
