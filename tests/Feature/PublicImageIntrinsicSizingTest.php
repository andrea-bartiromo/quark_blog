<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use App\Support\PublicImageDimensions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PublicImageIntrinsicSizingTest extends TestCase
{
    use RefreshDatabase;

    private const PORTRAIT = 'turing/portraits/alan-turing-portrait.png';

    public function test_dimension_resolver_reads_real_local_metadata_and_rejects_unsafe_paths(): void
    {
        $this->assertSame([1122, 1402], PublicImageDimensions::forUrl(asset('assets/img/'.self::PORTRAIT)));
        $this->assertNull(PublicImageDimensions::forUrl('/assets/img/../segreto.jpg'));
        $this->assertNull(PublicImageDimensions::forUrl('https://example.com/external.jpg'));
    }

    public function test_special_chapter_and_timeline_emit_real_intrinsic_dimensions(): void
    {
        $chapter = Blade::render('<x-special.chapter-opener image="'.self::PORTRAIT.'" alt="Ritratto" />');
        $timeline = Blade::render(
            '<x-special.timeline :events="$events" />',
            ['events' => [['year' => '1936', 'title' => 'Evento', 'image' => self::PORTRAIT]]]
        );

        foreach ([$chapter, $timeline] as $html) {
            $this->assertStringContainsString('width="1122"', $html);
            $this->assertStringContainsString('height="1402"', $html);
        }
    }

    public function test_turing_portrait_declares_dimensions_from_the_committed_file(): void
    {
        $html = Blade::render("@include('turing.partials.hero')", [
            'hero' => [],
            'heroBackgroundImage' => null,
            'bg' => static fn () => '',
        ]);

        $this->assertStringContainsString('width="1122"', $html);
        $this->assertStringContainsString('height="1402"', $html);
    }

    /**
     * Cantiere 65 (programma "100 cantieri Kairus"): <x-turing.article.figure>,
     * usato per le 12 immagini editoriali di Enigma, non risolveva mai le
     * proprie dimensioni da solo — le 10 figure hardcoded le passano come
     * prop letterali, ma l'unico punto realmente CMS-driven (l'anatomia
     * della macchina quando un editor carica una propria immagine,
     * $anatomyIsCms in enigma.blade.php) non ne passava nessuna: nessuno
     * spazio riservato dal browser prima del caricamento. Stesso
     * meccanismo automatico già in uso da <x-special.chapter-opener>.
     */
    public function test_turing_article_figure_auto_resolves_dimensions_when_not_explicitly_passed(): void
    {
        // I veri call site di enigma.blade.php passano sempre l'URL già
        // risolto tramite asset(), mai un percorso nudo (che il
        // componente, per gli altri chiamanti, risolverebbe invece sotto
        // assets/img/).
        $html = Blade::render(
            '<x-turing.article.figure :image="$image" alt="Plugboard" />',
            ['image' => asset('images/turing/enigma/plugboard.png')]
        );

        $this->assertStringContainsString('width="1672"', $html);
        $this->assertStringContainsString('height="941"', $html);
    }

    public function test_turing_article_figure_explicit_dimensions_still_take_precedence(): void
    {
        // Stesso URL pre-risolto dell'altro test: senza questo, il percorso
        // nudo verrebbe ri-prefissato sotto assets/img/ dal componente,
        // PublicImageDimensions::forUrl() non troverebbe alcun file reale e
        // la precedenza dell'esplicito non verrebbe davvero esercitata.
        $html = Blade::render(
            '<x-turing.article.figure :image="$image" alt="Plugboard" width="2400" height="1600" />',
            ['image' => asset('images/turing/enigma/plugboard.png')]
        );

        $this->assertStringContainsString('width="2400"', $html);
        $this->assertStringContainsString('height="1600"', $html);
        $this->assertStringNotContainsString('width="1672"', $html);
    }

    public function test_enigma_cms_override_anatomy_figure_declares_real_dimensions(): void
    {
        config(['turing.chapters_public' => true]);

        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'is_active' => true,
            'content' => [
                'editorial_blocks' => [
                    ['key' => 'enigma', 'enabled' => true, 'image' => 'turing/enigma.webp'],
                ],
            ],
        ]);

        $html = $this->get(route('turing.enigma'))->assertOk()->getContent();

        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="675"', $html);
    }

    /**
     * Stesso finding, applicato al campo CMS opzionale "why_items"
     * dell'hub (turing/partials/legacy-section.blade.php): nessuna
     * dimensione dichiarata finora se un editor imposta un'immagine.
     */
    public function test_legacy_section_why_item_image_declares_real_dimensions_when_set(): void
    {
        $why = collect([
            ['title' => 'Idea chiave', 'text' => 'Testo.', 'image' => self::PORTRAIT],
        ]);

        $html = Blade::render("@include('turing.partials.legacy-section')", [
            'why' => [],
            'whyItems' => $why,
            'whyBackgroundImage' => null,
            'whyPanelImage' => null,
            'previewMode' => false,
            'bg' => static fn () => '',
            'img' => static fn (string $value) => asset('assets/img/'.$value),
        ]);

        $this->assertStringContainsString('width="1122"', $html);
        $this->assertStringContainsString('height="1402"', $html);
    }
}
