<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SpecialSectionHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_kicker_title_and_text(): void
    {
        $html = Blade::render(
            '<x-special.section-header :kicker="$kicker" :title="$title" :text="$text" />',
            ['kicker' => 'Kicker', 'title' => 'Titolo', 'text' => 'Testo introduttivo.']
        );

        $this->assertStringContainsString('Kicker', $html);
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Titolo', $html);
        $this->assertStringContainsString('Testo introduttivo.', $html);
    }

    public function test_renders_nothing_when_all_fields_are_empty(): void
    {
        $html = Blade::render('<x-special.section-header />');

        $this->assertSame('', trim($html));
    }

    public function test_omits_kicker_and_text_when_not_provided(): void
    {
        $html = Blade::render(
            '<x-special.section-header :title="$title" />',
            ['title' => 'Solo titolo']
        );

        $this->assertStringContainsString('Solo titolo', $html);
        $this->assertStringNotContainsString('turing-kicker', $html);
        $this->assertStringNotContainsString('sp-section-header__text', $html);
    }

    public function test_level_is_normalized_to_h2_when_invalid(): void
    {
        $html = Blade::render(
            '<x-special.section-header :title="$title" level="script" />',
            ['title' => 'Titolo']
        );

        $this->assertStringContainsString('<h2 class="sp-section-header__title">Titolo</h2>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_level_accepts_an_allowed_alternative(): void
    {
        $html = Blade::render(
            '<x-special.section-header :title="$title" level="h3" />',
            ['title' => 'Titolo']
        );

        $this->assertStringContainsString('<h3 class="sp-section-header__title">Titolo</h3>', $html);
    }

    public function test_align_is_normalized_to_center_when_invalid(): void
    {
        $html = Blade::render(
            '<x-special.section-header :title="$title" align="justify" />',
            ['title' => 'Titolo']
        );

        $this->assertStringContainsString('sp-section-header--center', $html);
        $this->assertStringNotContainsString('sp-section-header--justify', $html);
    }

    public function test_variant_is_normalized_to_section_when_invalid(): void
    {
        $html = Blade::render(
            '<x-special.section-header :title="$title" variant="hero" />',
            ['title' => 'Titolo']
        );

        $this->assertStringContainsString('sp-section-header--section', $html);
        $this->assertStringNotContainsString('sp-section-header--hero', $html);
    }

    public function test_accepts_the_documented_panel_and_final_variants(): void
    {
        $panel = Blade::render(
            '<x-special.section-header :title="$title" variant="panel" align="left" />',
            ['title' => 'Titolo']
        );
        $final = Blade::render(
            '<x-special.section-header :title="$title" variant="final" align="center" />',
            ['title' => 'Titolo']
        );

        $this->assertStringContainsString('sp-section-header--panel', $panel);
        $this->assertStringContainsString('sp-section-header--left', $panel);
        $this->assertStringContainsString('sp-section-header--final', $final);
        $this->assertStringContainsString('sp-section-header--center', $final);
    }

    public function test_kicker_carries_both_the_legacy_and_the_new_class(): void
    {
        // Il doppio nome preserva la cascata colore/has-bg di turing.css
        // (classe legacy) offrendo al contempo una base autonoma sui token
        // --sp-* (classe nuova) — si vedano Decision #008 e il commento nel
        // componente. Entrambe devono comparire sullo stesso elemento.
        $html = Blade::render(
            '<x-special.section-header :kicker="$kicker" />',
            ['kicker' => 'Kicker']
        );

        $this->assertMatchesRegularExpression(
            '/<p class="turing-kicker sp-section-header__kicker">Kicker<\/p>/',
            $html
        );
    }

    public function test_caller_supplied_class_is_merged_with_component_classes(): void
    {
        // Meccanismo su cui si basa la migrazione di intro-section.blade.php:
        // la classe legacy passata dal chiamante (qui 'turing-section__head')
        // deve comparire accanto a quelle del componente sullo stesso elemento
        // radice, non sostituirle né finire altrove.
        $html = Blade::render(
            '<x-special.section-header class="turing-section__head" :title="$title" variant="section" align="center" />',
            ['title' => 'Titolo']
        );

        $this->assertStringContainsString(
            'class="sp-section-header sp-section-header--section sp-section-header--center turing-section__head"',
            $html
        );
    }

    public function test_title_is_escaped(): void
    {
        $html = Blade::render(
            '<x-special.section-header :title="$title" />',
            ['title' => '<script>alert(1)</script>']
        );

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_turing_page_still_renders_migrated_section_headers(): void
    {
        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('Dalla crittografia alla coscienza artificiale')
            ->assertSeeText('Scegli da dove iniziare');
    }
}
