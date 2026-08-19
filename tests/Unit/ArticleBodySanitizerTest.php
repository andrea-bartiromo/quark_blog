<?php

namespace Tests\Unit;

use App\Services\ArticleBodySanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleBodySanitizerTest extends TestCase
{
    private function sanitizer(): ArticleBodySanitizer
    {
        return new ArticleBodySanitizer;
    }

    #[DataProvider('maliciousPayloads')]
    public function test_strips_dangerous_content(string $input, string $mustNotContain): void
    {
        $output = $this->sanitizer()->sanitize($input);

        $this->assertStringNotContainsString($mustNotContain, $output);
    }

    public static function maliciousPayloads(): array
    {
        return [
            'script tag' => ['<p>ciao</p><script>alert(1)</script>', '<script'],
            'img onerror' => ['<p>ciao <img src=x onerror=alert(1)></p>', 'onerror'],
            'a onclick' => ['<p><a href="/x" onclick="alert(1)">link</a></p>', 'onclick'],
            'javascript href' => ['<p><a href="javascript:alert(1)">link</a></p>', 'javascript:'],
            'data href' => ['<p><a href="data:text/html,<script>alert(1)</script>">link</a></p>', 'data:'],
            'iframe' => ['<p>ciao</p><iframe src="https://evil.test"></iframe>', '<iframe'],
            'style tag' => ['<style>body{display:none}</style><p>ciao</p>', '<style'],
            'svg onload' => ['<svg onload=alert(1)><p>ciao</p></svg>', 'onload'],
            'form' => ['<form action="https://evil.test"><input></form>', '<form'],
            'object embed' => ['<object data="evil.swf"></object><embed src="evil.swf">', '<object'],
        ];
    }

    #[DataProvider('legitimateContent')]
    public function test_preserves_legitimate_editorial_content(string $input): void
    {
        $output = $this->sanitizer()->sanitize($input);

        $this->assertStringContainsString('Testo', $output);
    }

    public static function legitimateContent(): array
    {
        return [
            'paragraphs' => ['<p>Testo del primo paragrafo.</p><p>Secondo paragrafo.</p>'],
            'heading' => ['<h2>Titolo</h2><p>Testo del corpo.</p>'],
            'bold and italic' => ['<p><strong>Testo</strong> in grassetto e <em>corsivo</em>.</p>'],
            'lists' => ['<ul><li>Testo primo punto</li><li>secondo</li></ul>'],
            'safe link' => ['<p>Testo con <a href="https://example.test">un link</a>.</p>'],
            'blockquote' => ['<blockquote>Testo citato.</blockquote>'],
        ];
    }

    public function test_safe_link_keeps_its_href(): void
    {
        $output = $this->sanitizer()->sanitize('<p><a href="https://example.test/pagina">link</a></p>');

        $this->assertStringContainsString('href="https://example.test/pagina"', $output);
    }

    public function test_unknown_tag_is_unwrapped_but_its_text_survives(): void
    {
        $output = $this->sanitizer()->sanitize('<div class="x">Testo dentro un div sconosciuto</div>');

        $this->assertStringContainsString('Testo dentro un div sconosciuto', $output);
        $this->assertStringNotContainsString('<div', $output);
    }

    public function test_target_blank_link_gets_noopener_noreferrer(): void
    {
        $output = $this->sanitizer()->sanitize('<p><a href="https://example.test" target="_blank">link</a></p>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $output);
    }

    public function test_empty_body_is_returned_unchanged(): void
    {
        $this->assertSame('', $this->sanitizer()->sanitize(''));
    }
}
