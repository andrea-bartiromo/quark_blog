<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SpecialModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_dialog_semantics_and_slot_content(): void
    {
        $html = Blade::render(
            '<x-special.modal id="demo" title="Titolo"><p>Contenuto</p></x-special.modal>'
        );

        $this->assertStringContainsString('id="demo"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="demo-title"', $html);
        $this->assertStringContainsString('id="demo-title"', $html);
        $this->assertStringContainsString('Titolo', $html);
        $this->assertStringContainsString('<p>Contenuto</p>', $html);
    }

    public function test_renders_hidden_by_default(): void
    {
        $html = Blade::render('<x-special.modal id="demo">Contenuto</x-special.modal>');

        $this->assertStringContainsString('hidden', $html);
    }

    public function test_omits_aria_labelledby_when_title_is_not_provided(): void
    {
        $html = Blade::render('<x-special.modal id="demo">Contenuto</x-special.modal>');

        $this->assertStringNotContainsString('aria-labelledby', $html);
        $this->assertStringNotContainsString('sp-modal__title', $html);
    }

    public function test_close_button_carries_the_close_label(): void
    {
        $html = Blade::render(
            '<x-special.modal id="demo" close-label="Chiudi finestra">Contenuto</x-special.modal>'
        );

        $this->assertStringContainsString('aria-label="Chiudi finestra"', $html);
    }

    public function test_close_button_defaults_to_a_safe_label(): void
    {
        $html = Blade::render('<x-special.modal id="demo">Contenuto</x-special.modal>');

        $this->assertStringContainsString('aria-label="Chiudi"', $html);
    }

    public function test_size_is_normalized_to_md_when_invalid(): void
    {
        $html = Blade::render('<x-special.modal id="demo" size="huge">Contenuto</x-special.modal>');

        $this->assertStringContainsString('sp-modal--md', $html);
        $this->assertStringNotContainsString('sp-modal--huge', $html);
    }

    public function test_size_accepts_the_documented_alternatives(): void
    {
        $small = Blade::render('<x-special.modal id="demo" size="sm">Contenuto</x-special.modal>');
        $large = Blade::render('<x-special.modal id="demo" size="lg">Contenuto</x-special.modal>');

        $this->assertStringContainsString('sp-modal--sm', $small);
        $this->assertStringContainsString('sp-modal--lg', $large);
    }

    public function test_caller_supplied_class_is_merged_with_component_classes(): void
    {
        $html = Blade::render(
            '<x-special.modal id="demo" class="extra-class">Contenuto</x-special.modal>'
        );

        $this->assertStringContainsString('class="sp-modal sp-modal--md extra-class"', $html);
    }

    public function test_title_and_slot_content_are_escaped(): void
    {
        $html = Blade::render(
            '<x-special.modal id="demo" :title="$title">{{ $body }}</x-special.modal>',
            ['title' => '<script>alert(1)</script>', 'body' => '<script>alert(2)</script>']
        );

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html);
    }

    public function test_turing_page_still_renders_without_any_modal_instance(): void
    {
        // Decision #009: il componente e' pronto ma non ancora collegato a
        // nessuna vista Turing in questa PR (fuori scope).
        $response = $this->get(route('turing'));

        $response->assertOk();
    }
}
