<?php

namespace Tests\Feature;

use App\Support\PublicImageDimensions;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PublicImageIntrinsicSizingTest extends TestCase
{
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
}
