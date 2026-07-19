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
            ->assertDontSeeText('Computabilità e macchina universale');
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

    public function test_disabled_cms_blocks_are_treated_as_intentional_content(): void
    {
        $this->createTuringPage([
            'editorial_blocks' => [
                [
                    'enabled' => false,
                    'key' => 'disabled-cms-block',
                    'layout' => 'image_left',
                    'kicker' => 'CMS',
                    'title' => 'Blocco CMS disabilitato',
                    'text' => 'Questo blocco è stato disabilitato dagli editor.',
                    'image' => 'turing/enigma.webp',
                    'background_image' => 'turing-enigma-background.webp',
                    'link_label' => '',
                    'link_url' => '#disabled-cms-block',
                ],
            ],
            'timeline' => [],
        ]);

        $response = $this->get(route('turing'));

        $response
            ->assertOk()
            ->assertDontSeeText('Blocco CMS disabilitato')
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
