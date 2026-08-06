<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * <x-media.image-viewer> non conosce Article né alcun altro modello: questi
 * test lo esercitano in isolamento, passandogli solo stringhe, esattamente
 * come farebbe qualunque pagina che lo adotta (oggi solo la copertina
 * dell'articolo, in futuro anche lo Speciale Turing).
 */
class MediaImageViewerTest extends TestCase
{
    private function render(string $props, array $data = []): string
    {
        return Blade::render('<x-media.image-viewer '.$props.' />', $data);
    }

    public function test_renders_a_link_trigger_pointing_directly_at_the_image(): void
    {
        $html = $this->render(
            'src="/assets/img/cover.jpg" alt="Un microscopio elettronico"'
        );

        // Il trigger e' un <a href> reale: senza JavaScript, cliccarlo apre
        // comunque il file — degradazione, non un vicolo cieco.
        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*href="\/assets\/img\/cover\.jpg"[^>]*data-media-viewer-target="[^"]+"/s',
            $html
        );
    }

    public function test_dialog_markup_is_accessible(): void
    {
        $html = $this->render(
            'src="/assets/img/cover.jpg" alt="Un microscopio elettronico"'
        );

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertMatchesRegularExpression('/aria-labelledby="[^"]+-title"/', $html);
        $this->assertStringContainsString('hidden', $html);

        // Il titolo accessibile referenziato da aria-labelledby esiste
        // davvero nel markup, con lo stesso id.
        preg_match('/aria-labelledby="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches);
        $this->assertStringContainsString('id="'.$matches[1].'"', $html);
    }

    public function test_title_falls_back_to_alt_when_absent(): void
    {
        $html = $this->render(
            'src="/assets/img/cover.jpg" alt="Testo alternativo di riserva"'
        );

        $this->assertStringContainsString(
            '<h2 id="'.$this->extractTitleId($html).'" class="media-viewer__title">Testo alternativo di riserva</h2>',
            $html
        );
    }

    public function test_shows_only_the_fields_that_are_actually_filled(): void
    {
        $html = $this->render(
            'src="/x.jpg" alt="Alt" :caption="$caption" :credit="null" :source="null" :license="null"',
            ['caption' => 'Una didascalia editoriale']
        );

        $this->assertStringContainsString('Una didascalia editoriale', $html);
        $this->assertStringNotContainsString('<dt>Credito</dt>', $html);
        $this->assertStringNotContainsString('<dt>Fonte</dt>', $html);
        $this->assertStringNotContainsString('<dt>Licenza</dt>', $html);
    }

    public function test_omits_the_entire_facts_block_when_no_optional_metadata_is_present(): void
    {
        $html = $this->render('src="/x.jpg" alt="Alt"');

        $this->assertStringNotContainsString('media-viewer__dl', $html);
        $this->assertStringNotContainsString('media-viewer__caption', $html);
    }

    public function test_source_link_opens_in_a_new_tab_safely(): void
    {
        $html = $this->render(
            'src="/x.jpg" alt="Alt" :source="$source" :sourceUrl="$url"',
            ['source' => 'NASA', 'url' => 'https://nasa.gov']
        );

        $this->assertMatchesRegularExpression(
            '/<a href="https:\/\/nasa\.gov" target="_blank" rel="noopener noreferrer">NASA<\/a>/',
            $html
        );
    }

    public function test_invalid_source_url_is_not_rendered_as_a_link(): void
    {
        $html = $this->render(
            'src="/x.jpg" alt="Alt" :source="$source" :sourceUrl="$url"',
            ['source' => 'Archivio interno', 'url' => 'non-una-url']
        );

        $this->assertStringContainsString('Archivio interno', $html);
        $this->assertStringNotContainsString('<a href="non-una-url"', $html);
    }

    public function test_missing_source_url_still_shows_the_source_name_as_plain_text(): void
    {
        $html = $this->render(
            'src="/x.jpg" alt="Alt" :source="$source"',
            ['source' => 'Archivio di famiglia']
        );

        $this->assertStringContainsString('<dd>', $html);
        $this->assertStringContainsString('Archivio di famiglia', $html);
        $this->assertStringNotContainsString('<a href', $html);
    }

    public function test_multiple_instances_on_the_same_page_get_unique_ids(): void
    {
        $html = Blade::render(
            '<x-media.image-viewer src="/a.jpg" alt="Prima immagine" />'
            .'<x-media.image-viewer src="/b.jpg" alt="Seconda immagine" />'
        );

        preg_match_all('/data-media-viewer-target="([^"]+)"/', $html, $targets);
        preg_match_all('/<div\s+class="media-viewer"[\s\S]*?id="([^"]+)"/', $html, $roots);

        $this->assertCount(2, $targets[1]);
        $this->assertCount(2, $roots[1]);
        $this->assertNotSame($targets[1][0], $targets[1][1]);
        $this->assertSame($targets[1], $roots[1]);
    }

    public function test_explicit_id_override_is_respected(): void
    {
        $html = $this->render(
            'src="/x.jpg" alt="Alt" id="cover-viewer"'
        );

        $this->assertStringContainsString('id="cover-viewer"', $html);
        $this->assertStringContainsString('data-media-viewer-target="cover-viewer"', $html);
    }

    public function test_zoom_controls_render_when_enabled_and_are_absent_when_disabled(): void
    {
        $withZoom = $this->render('src="/x.jpg" alt="Alt"');
        $this->assertStringContainsString('data-media-viewer-zoom-in', $withZoom);

        $withoutZoom = $this->render('src="/x.jpg" alt="Alt" :enable-zoom="false"');
        $this->assertStringNotContainsString('data-media-viewer-zoom-in', $withoutZoom);
    }

    public function test_no_interactive_control_is_nested_inside_another(): void
    {
        $html = $this->render(
            'src="/x.jpg" alt="Alt" :source="$source" :sourceUrl="$url"',
            ['source' => 'NASA', 'url' => 'https://nasa.gov']
        );

        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>');
        $xpath = new \DOMXPath($dom);

        $this->assertSame(
            0,
            $xpath->query('//button[.//a or .//button] | //a[.//a or .//button]')->length
        );
    }

    public function test_known_local_image_gets_intrinsic_dimensions_without_explicit_props(): void
    {
        // Riusa lo stesso asset gia' verificato per il fix del layout shift
        // di Enigma: dimensioni reali note, file davvero presente sul disco.
        $path = public_path('images/turing/enigma/cutaway-enigma.png');
        if (! is_file($path)) {
            $this->markTestSkipped('Asset di riferimento non presente in questo ambiente.');
        }

        [$width, $height] = getimagesize($path);

        $html = $this->render(
            'src="/images/turing/enigma/cutaway-enigma.png" alt="Alt"'
        );

        $this->assertStringContainsString('width="'.$width.'"', $html);
        $this->assertStringContainsString('height="'.$height.'"', $html);
    }

    public function test_unresolvable_remote_image_degrades_without_dimension_attributes(): void
    {
        $html = $this->render(
            'src="https://example.org/una-immagine-esterna-inesistente.jpg" alt="Alt"'
        );

        preg_match('/<img\b[^>]*data-media-viewer-image[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches, 'La <img> del dialog non e\' stata trovata.');

        $this->assertStringNotContainsString('width="', $matches[0]);
        $this->assertStringNotContainsString('height="', $matches[0]);
    }

    private function extractTitleId(string $html): string
    {
        preg_match('/aria-labelledby="([^"]+)"/', $html, $matches);

        return $matches[1] ?? '';
    }
}
