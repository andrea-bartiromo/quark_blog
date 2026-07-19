<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuringPageFallbacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_turing_page_uses_fallbacks_when_special_page_record_is_missing(): void
    {
        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertSeeText('Computabilità e macchina universale');
    }

    public function test_turing_page_uses_fallbacks_when_content_is_empty(): void
    {
        $this->createTuringPage([]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertSeeText('Computabilità e macchina universale');
    }

    public function test_turing_page_uses_fallbacks_when_editorial_blocks_and_timeline_are_empty(): void
    {
        $this->createTuringPage([
            'editorial_blocks' => [],
            'timeline' => [],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertSeeText('Computabilità e macchina universale');
    }

    public function test_turing_page_uses_fallbacks_when_editorial_blocks_are_structurally_empty(): void
    {
        $this->createTuringPage([
            'editorial_blocks' => [[]],
            'timeline' => [
                [
                    'year' => '2099',
                    'title' => 'Timeline CMS valida',
                    'text' => 'Evento CMS valido.',
                ],
            ],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertSeeText('Timeline CMS valida');
    }

    public function test_turing_page_uses_fallbacks_when_timeline_is_structurally_empty(): void
    {
        $this->createTuringPage([
            'editorial_blocks' => [
                [
                    'enabled' => true,
                    'title' => 'Blocco CMS valido',
                    'text' => 'Testo CMS valido.',
                ],
            ],
            'timeline' => [[]],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('Blocco CMS valido')
            ->assertSeeText('Computabilità e macchina universale');
    }

    public function test_turing_page_filters_empty_timeline_items_without_merging_fallbacks(): void
    {
        $this->createTuringPage([
            'timeline' => [
                [],
                [
                    'year' => '2099',
                    'title' => 'Evento CMS valido',
                    'text' => 'Contenuto CMS.',
                ],
            ],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('Evento CMS valido')
            ->assertDontSeeText('La nascita a Londra');
    }

    public function test_turing_page_uses_cms_content_without_merging_fallbacks(): void
    {
        $this->createTuringPage([
            'editorial_blocks' => [
                [
                    'enabled' => true,
                    'key' => 'cms-block',
                    'layout' => 'image_left',
                    'kicker' => 'CMS',
                    'title' => 'Blocco CMS personalizzato',
                    'text' => 'Testo personalizzato salvato dal CMS.',
                    'image' => 'turing/enigma.webp',
                    'background_image' => 'turing-enigma-background.webp',
                    'link_label' => 'Azione CMS',
                    'link_url' => '#cms-block',
                ],
            ],
            'timeline' => [
                [
                    'year' => '2099',
                    'title' => 'Evento CMS personalizzato',
                    'text' => 'Evento salvato dal CMS.',
                ],
            ],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('Blocco CMS personalizzato')
            ->assertSeeText('Evento CMS personalizzato')
            ->assertDontSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertDontSeeText('La nascita a Londra');
    }

    public function test_turing_route_cards_without_valid_urls_are_not_rendered_as_empty_links(): void
    {
        $this->createTuringPage([
            'cards' => [
                [
                    'label' => 'Card senza URL',
                    'title' => 'Percorso senza destinazione',
                    'text' => 'Questa card resta informativa.',
                    'style' => 'legacy',
                ],
                [
                    'label' => 'Card con cancelletto',
                    'title' => 'Percorso non navigabile',
                    'text' => 'Non deve creare un link vuoto.',
                    'url' => '#',
                    'style' => 'enigma',
                ],
                [
                    'label' => 'Card valida',
                    'title' => 'Percorso navigabile',
                    'text' => 'Questa card mantiene il link.',
                    'url' => '/turing/enigma',
                    'style' => 'enigma',
                ],
            ],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('Percorso senza destinazione')
            ->assertSeeText('Percorso non navigabile')
            ->assertSee('href="/turing/enigma"', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_universal_machine_fallback_block_does_not_render_a_self_link_cta(): void
    {
        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('La macchina universale e l’idea moderna di programma')
            ->assertSee('id="macchina-universale"', false)
            ->assertDontSee('href="#macchina-universale"', false)
            ->assertDontSeeText('Segui il filo del calcolo');
    }

    public function test_turing_page_uses_fallbacks_when_content_values_are_invalid(): void
    {
        $this->createTuringPage([
            'editorial_blocks' => 'invalid',
            'timeline' => ['invalid-event'],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertSeeText('Computabilità e macchina universale');
    }

    public function test_minimal_disabled_cms_block_is_treated_as_intentional_content(): void
    {
        $this->createTuringPage([
            'editorial_blocks' => [
                ['enabled' => false],
            ],
            'timeline' => [],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertDontSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertSeeText('Computabilità e macchina universale');
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function createTuringPage(array $content): SpecialPage
    {
        return SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'description' => 'Speciale editoriale dedicato ad Alan Turing.',
            'content' => $content,
            'is_active' => true,
        ]);
    }
}
