<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 58 (programma "100 cantieri Kairus"). Le card dei capitoli
 * sull'hub /turing sono già un array ordinato dentro SpecialPage::content
 * (nessuna colonna "position" separata, l'ordine di rendering di
 * turing.blade.php è l'ordine dell'array) — prima di questo cantiere
 * l'admin "lite" le passava soltanto come campi nascosti (nessun modo di
 * riordinarle). Questi test coprono solo lo spostamento di card già
 * esistenti: nessun contenuto editoriale nuovo viene mai creato qui.
 */
class TuringChapterCardReorderTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function pageWithCards(array $cards): SpecialPage
    {
        return SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'is_active' => true,
            'content' => ['cards' => $cards],
        ]);
    }

    private function threeCards(): array
    {
        return [
            ['title' => 'La guerra di Enigma', 'url' => '/turing/enigma', 'style' => 'enigma'],
            ['title' => 'Dal Test di Turing agli LLM', 'url' => '/turing/ai', 'style' => 'ai'],
            ['title' => 'Il genio inquieto', 'url' => '/turing/legacy', 'style' => 'legacy'],
        ];
    }

    public function test_guest_cannot_move_a_card(): void
    {
        $this->pageWithCards($this->threeCards());

        $this->post(route('admin.turing.cards.move', 1), ['direction' => 'up'])
            ->assertRedirect(route('login'));
    }

    public function test_moving_a_card_up_swaps_it_with_the_previous_one(): void
    {
        $page = $this->pageWithCards($this->threeCards());

        $response = $this->actingAs($this->editor())
            ->post(route('admin.turing.cards.move', 1), ['direction' => 'up']);

        $response->assertRedirect(route('admin.turing').'#cards');

        $cards = $page->fresh()->content['cards'];
        $this->assertSame('Dal Test di Turing agli LLM', $cards[0]['title']);
        $this->assertSame('La guerra di Enigma', $cards[1]['title']);
        $this->assertSame('Il genio inquieto', $cards[2]['title']);
    }

    public function test_moving_a_card_down_swaps_it_with_the_next_one(): void
    {
        $page = $this->pageWithCards($this->threeCards());

        $this->actingAs($this->editor())
            ->post(route('admin.turing.cards.move', 0), ['direction' => 'down']);

        $cards = $page->fresh()->content['cards'];
        $this->assertSame('Dal Test di Turing agli LLM', $cards[0]['title']);
        $this->assertSame('La guerra di Enigma', $cards[1]['title']);
    }

    public function test_moving_the_first_card_up_is_rejected_without_changing_the_order(): void
    {
        $page = $this->pageWithCards($this->threeCards());

        $response = $this->actingAs($this->editor())
            ->post(route('admin.turing.cards.move', 0), ['direction' => 'up']);

        $response->assertSessionHasErrors('cards');

        $cards = $page->fresh()->content['cards'];
        $this->assertSame('La guerra di Enigma', $cards[0]['title']);
    }

    public function test_moving_the_last_card_down_is_rejected_without_changing_the_order(): void
    {
        $page = $this->pageWithCards($this->threeCards());

        $response = $this->actingAs($this->editor())
            ->post(route('admin.turing.cards.move', 2), ['direction' => 'down']);

        $response->assertSessionHasErrors('cards');

        $cards = $page->fresh()->content['cards'];
        $this->assertSame('Il genio inquieto', $cards[2]['title']);
    }

    public function test_moving_an_out_of_range_index_is_rejected(): void
    {
        $page = $this->pageWithCards($this->threeCards());

        $response = $this->actingAs($this->editor())
            ->post(route('admin.turing.cards.move', 99), ['direction' => 'up']);

        $response->assertSessionHasErrors('cards');

        $this->assertCount(3, $page->fresh()->content['cards']);
    }

    public function test_moving_a_card_preserves_all_of_its_fields(): void
    {
        $page = $this->pageWithCards([
            ['title' => 'Prima', 'url' => '/turing/enigma', 'style' => 'enigma', 'label' => '01 · Bletchley Park', 'text' => 'Testo A', 'image' => 'a.webp'],
            ['title' => 'Seconda', 'url' => '/turing/ai', 'style' => 'ai', 'label' => '02 · Macchine intelligenti', 'text' => 'Testo B', 'image' => 'b.webp'],
        ]);

        $this->actingAs($this->editor())
            ->post(route('admin.turing.cards.move', 0), ['direction' => 'down']);

        $cards = $page->fresh()->content['cards'];
        $this->assertSame([
            'title' => 'Seconda',
            'url' => '/turing/ai',
            'style' => 'ai',
            'label' => '02 · Macchine intelligenti',
            'text' => 'Testo B',
            'image' => 'b.webp',
        ], $cards[0]);
        $this->assertSame('Prima', $cards[1]['title']);
    }

    public function test_reordering_cards_changes_their_rendering_order_on_the_public_hub(): void
    {
        config(['turing.chapters_public' => true]);
        $page = $this->pageWithCards($this->threeCards());

        $this->actingAs($this->editor())
            ->post(route('admin.turing.cards.move', 0), ['direction' => 'down']);

        $html = $this->get(route('turing'))->assertOk()->getContent();

        $positionOfAi = strpos($html, 'Dal Test di Turing agli LLM');
        $positionOfEnigma = strpos($html, 'La guerra di Enigma');

        $this->assertNotFalse($positionOfAi);
        $this->assertNotFalse($positionOfEnigma);
        $this->assertLessThan($positionOfEnigma, $positionOfAi, 'dopo lo spostamento la card AI deve comparire prima della card Enigma nell\'hub pubblico.');
    }

    public function test_admin_turing_edit_page_shows_move_buttons_for_each_card(): void
    {
        $this->pageWithCards($this->threeCards());

        $response = $this->actingAs($this->editor())->get(route('admin.turing'));

        $response->assertOk();
        $response->assertSeeText('Ordine dei capitoli in evidenza');
        $response->assertSeeText('La guerra di Enigma');
        $response->assertSee('Sposta su: La guerra di Enigma', false);
    }

    public function test_the_first_cards_move_up_button_is_disabled(): void
    {
        $this->pageWithCards($this->threeCards());

        $html = $this->actingAs($this->editor())->get(route('admin.turing'))->getContent();

        $this->assertMatchesRegularExpression(
            '/disabled[^>]*aria-label="Sposta su: La guerra di Enigma"/',
            $html
        );
    }

    public function test_the_last_cards_move_down_button_is_disabled(): void
    {
        $this->pageWithCards($this->threeCards());

        $html = $this->actingAs($this->editor())->get(route('admin.turing'))->getContent();

        $this->assertMatchesRegularExpression(
            '/disabled[^>]*aria-label="Sposta giù: Il genio inquieto"/',
            $html
        );
    }
}
