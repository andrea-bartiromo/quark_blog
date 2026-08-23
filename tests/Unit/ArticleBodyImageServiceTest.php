<?php

namespace Tests\Unit;

use App\Services\ArticleBodyImageService;
use Tests\Concerns\InteractsWithTestImages;
use Tests\Concerns\UsesIsolatedPublicPath;
use Tests\TestCase;

class ArticleBodyImageServiceTest extends TestCase
{
    use InteractsWithTestImages;
    use UsesIsolatedPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedPublicPath();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedPublicPath();
        $this->tearDownTestImages();
        parent::tearDown();
    }

    private function service(): ArticleBodyImageService
    {
        return new ArticleBodyImageService;
    }

    /** Scrive un file immagine reale in assets/img/{diskName} (isolato per-test). */
    private function placeLocalImage(string $diskName, int $width, int $height): void
    {
        $file = $this->makeSolidImageUpload(basename($diskName), $width, $height);
        $target = public_path('assets/img/'.$diskName);
        @mkdir(dirname($target), 0775, true);
        rename($file->getPathname(), $target);
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

    // ---- S2-B: width/height intrinseci per immagini locali del corpo ----

    public function test_a_valid_local_image_without_width_or_height_gets_its_real_intrinsic_dimensions(): void
    {
        $this->placeLocalImage('articles/covers/inline-body.jpg', 1600, 900);

        $result = $this->service()->applyLazyLoading('<img src="/assets/img/articles/covers/inline-body.jpg" alt="Foto">');

        $this->assertStringContainsString('width="1600"', $result);
        $this->assertStringContainsString('height="900"', $result);
    }

    public function test_a_local_image_reached_via_an_absolute_same_origin_url_also_gets_dimensions(): void
    {
        $this->placeLocalImage('articles/covers/absolute-url.jpg', 1200, 800);
        $appUrl = rtrim((string) config('app.url'), '/');

        $result = $this->service()->applyLazyLoading('<img src="'.$appUrl.'/assets/img/articles/covers/absolute-url.jpg" alt="Foto">');

        $this->assertStringContainsString('width="1200"', $result);
        $this->assertStringContainsString('height="800"', $result);
    }

    public function test_an_image_that_already_declares_width_or_height_is_never_touched(): void
    {
        $this->placeLocalImage('articles/covers/already-sized.jpg', 1600, 900);

        $withWidth = $this->service()->applyLazyLoading(
            '<img src="/assets/img/articles/covers/already-sized.jpg" alt="Foto" width="42">'
        );
        $this->assertStringContainsString('width="42"', $withWidth);
        $this->assertStringNotContainsString('height="900"', $withWidth);

        $withHeight = $this->service()->applyLazyLoading(
            '<img src="/assets/img/articles/covers/already-sized.jpg" alt="Foto" height="42">'
        );
        $this->assertStringContainsString('height="42"', $withHeight);
        $this->assertStringNotContainsString('width="1600"', $withHeight);
    }

    public function test_preexisting_inline_style_and_other_attributes_are_preserved_alongside_added_dimensions(): void
    {
        $this->placeLocalImage('articles/covers/styled.jpg', 640, 480);

        $result = $this->service()->applyLazyLoading(
            '<img src="/assets/img/articles/covers/styled.jpg" alt="Foto" class="figure" style="border-radius:8px">'
        );

        $this->assertStringContainsString('class="figure"', $result);
        $this->assertStringContainsString('style="border-radius:8px"', $result);
        $this->assertStringContainsString('width="640"', $result);
        $this->assertStringContainsString('height="480"', $result);
    }

    public function test_an_external_image_is_never_fetched_and_never_gets_dimensions(): void
    {
        $result = $this->service()->applyLazyLoading('<img src="https://esempio-esterno.test/foto.jpg" alt="Foto">');

        $this->assertStringNotContainsString('width=', $result);
        $this->assertStringNotContainsString('height=', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function test_an_image_with_a_different_host_even_with_a_local_looking_path_is_treated_as_external(): void
    {
        $this->placeLocalImage('articles/covers/lookalike.jpg', 900, 600);

        $result = $this->service()->applyLazyLoading(
            '<img src="https://un-altro-sito.test/assets/img/articles/covers/lookalike.jpg" alt="Foto">'
        );

        $this->assertStringNotContainsString('width=', $result);
        $this->assertStringNotContainsString('height=', $result);
    }

    public function test_an_image_with_no_src_attribute_is_left_without_dimensions(): void
    {
        $result = $this->service()->applyLazyLoading('<img alt="Foto senza src">');

        $this->assertStringNotContainsString('width=', $result);
        $this->assertStringNotContainsString('height=', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function test_a_local_path_pointing_to_a_file_that_does_not_exist_is_left_without_dimensions(): void
    {
        $result = $this->service()->applyLazyLoading(
            '<img src="/assets/img/articles/covers/non-esiste-davvero.jpg" alt="Foto">'
        );

        $this->assertStringNotContainsString('width=', $result);
        $this->assertStringNotContainsString('height=', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
    }

    public function test_a_path_outside_the_local_media_root_is_never_treated_as_local(): void
    {
        // "/storage/..." e' una radice diversa (vedi audit PR #247 su
        // author->photo): non deve mai essere trattata come locale da
        // questo servizio, che riconosce solo /assets/img/.
        $result = $this->service()->applyLazyLoading('<img src="/storage/avatars/foto.jpg" alt="Foto">');

        $this->assertStringNotContainsString('width=', $result);
        $this->assertStringNotContainsString('height=', $result);
    }

    public function test_a_path_traversal_attempt_is_never_resolved(): void
    {
        $result = $this->service()->applyLazyLoading(
            '<img src="/assets/img/../../.env" alt="Foto">'
        );

        $this->assertStringNotContainsString('width=', $result);
        $this->assertStringNotContainsString('height=', $result);
    }

    public function test_multiple_images_each_get_their_own_correct_dimensions_when_resolvable(): void
    {
        $this->placeLocalImage('articles/covers/multi-a.jpg', 400, 300);
        $this->placeLocalImage('articles/covers/multi-b.jpg', 800, 200);

        $result = $this->service()->applyLazyLoading(
            '<img src="/assets/img/articles/covers/multi-a.jpg" alt="A">'
            .'<img src="https://esempio-esterno.test/b.jpg" alt="B">'
            .'<img src="/assets/img/articles/covers/multi-b.jpg" alt="C">'
        );

        $this->assertStringContainsString('width="400"', $result);
        $this->assertStringContainsString('height="300"', $result);
        $this->assertStringContainsString('width="800"', $result);
        $this->assertStringContainsString('height="200"', $result);
        $this->assertSame(2, substr_count($result, 'width='));
    }
}
