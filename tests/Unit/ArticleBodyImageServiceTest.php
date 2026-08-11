<?php

namespace Tests\Unit;

use App\Services\ArticleBodyImageService;
use Tests\TestCase;

class ArticleBodyImageServiceTest extends TestCase
{
    private function service(): ArticleBodyImageService
    {
        return new ArticleBodyImageService;
    }

    public function test_an_image_without_a_loading_attribute_gets_lazy_loading_and_async_decoding(): void
    {
        $result = $this->service()->applyLazyLoading('<img src="/a.jpg" alt="Una foto">');

        $this->assertStringContainsString('loading="lazy"', $result);
        $this->assertStringContainsString('decoding="async"', $result);
    }

    public function test_an_image_that_already_declares_loading_is_left_untouched(): void
    {
        $result = $this->service()->applyLazyLoading('<img src="/a.jpg" alt="Una foto" loading="eager">');

        $this->assertStringContainsString('loading="eager"', $result);
        $this->assertStringNotContainsString('loading="lazy"', $result);
    }

    public function test_an_image_that_already_declares_decoding_is_left_untouched(): void
    {
        $result = $this->service()->applyLazyLoading('<img src="/a.jpg" alt="Una foto" decoding="sync">');

        $this->assertStringContainsString('decoding="sync"', $result);
        $this->assertStringNotContainsString('decoding="async"', $result);
    }

    public function test_multiple_images_are_all_updated(): void
    {
        $result = $this->service()->applyLazyLoading('<p>Testo.</p><img src="/a.jpg" alt="A"><p>Altro.</p><img src="/b.jpg" alt="B">');

        $this->assertSame(2, substr_count($result, 'loading="lazy"'));
    }

    public function test_html_without_images_is_returned_unchanged(): void
    {
        $html = '<p>Solo testo, nessuna immagine.</p>';

        $this->assertSame($html, $this->service()->applyLazyLoading($html));
    }

    public function test_empty_html_is_returned_unchanged(): void
    {
        $this->assertSame('', $this->service()->applyLazyLoading(''));
    }

    public function test_non_image_content_and_text_are_preserved(): void
    {
        $result = $this->service()->applyLazyLoading('<h2>Titolo</h2><p>Testo con <strong>enfasi</strong>.</p><img src="/a.jpg" alt="A">');

        $this->assertStringContainsString('<h2>Titolo</h2>', $result);
        $this->assertStringContainsString('<strong>enfasi</strong>', $result);
    }

    /**
     * Trovato in review (Codex): un tag di chiusura senza apertura nel
     * corpo salvato (tollerato dai browser, quindi realisticamente
     * presente in articoli esistenti) veniva interpretato dal parser come
     * chiusura anticipata del wrapper sintetico usato internamente — tutto
     * cio' che seguiva, testo e immagini, spariva dal rendering. Deve
     * restituire l'HTML originale invariato piuttosto che troncare
     * l'articolo.
     */
    public function test_an_unmatched_closing_tag_never_truncates_the_article(): void
    {
        $html = '<p>Prima parte.</p></div><p>Seconda parte con <img src="/a.jpg" alt="A"></p>';

        $result = $this->service()->applyLazyLoading($html);

        $this->assertSame($html, $result);
        $this->assertStringContainsString('Seconda parte', $result);
    }
}
