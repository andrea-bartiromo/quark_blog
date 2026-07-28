<?php

namespace Tests\Feature;

use App\Models\SpecialPage;
use App\Models\User;
use Database\Seeders\TuringSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TuringSeederTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    // firstOrCreate() su 'slug' deve rendere il seeder sicuro da eseguire
    // più volte: nessuna riga duplicata, nessun errore di vincolo unique.
    public function test_running_the_seeder_twice_does_not_create_duplicate_rows(): void
    {
        $this->seed(TuringSeeder::class);
        $this->seed(TuringSeeder::class);

        $this->assertSame(1, SpecialPage::where('slug', 'turing')->count());
    }

    public function test_running_the_seeder_twice_produces_the_same_content(): void
    {
        $this->seed(TuringSeeder::class);
        $first = SpecialPage::where('slug', 'turing')->firstOrFail()->content;

        $this->seed(TuringSeeder::class);
        $second = SpecialPage::where('slug', 'turing')->firstOrFail()->content;

        $this->assertSame($first, $second);
    }

    public function test_running_the_seeder_does_not_overwrite_existing_cms_content(): void
    {
        $this->seed(TuringSeeder::class);

        $page = SpecialPage::where('slug', 'turing')->firstOrFail();

        $page->update([
            'title' => 'Titolo modificato dal CMS',
            'description' => 'Descrizione personalizzata',
        ]);

        $this->seed(TuringSeeder::class);

        $page->refresh();

        $this->assertSame('Titolo modificato dal CMS', $page->title);
        $this->assertSame('Descrizione personalizzata', $page->description);
    }

    // TuringSeeder non scrive alcuna chiave 'timeline' (vedi commento nel
    // file): farlo disattiverebbe la Timeline a capitoli (Decision #003), una
    // regressione esplicitamente da evitare. Gli approfondimenti degli eventi
    // di default sono quindi "inizializzati" dai default del controller, non
    // da una riga scritta dal seeder — ma per un'installazione appena
    // seedata il risultato e' lo stesso: subito disponibili, senza alcun
    // intervento manuale.
    public function test_seeder_leaves_default_timeline_details_to_the_controller_and_does_not_disable_chapters(): void
    {
        $this->seed(TuringSeeder::class);

        $content = SpecialPage::where('slug', 'turing')->firstOrFail()->content;
        $this->assertArrayNotHasKey('timeline', $content);

        $this->get(route('turing'))
            ->assertOk()
            ->assertSee('data-sp-modal-target="timeline-chapter-1-event-0"', false);
    }

    public function test_reseeding_does_not_overwrite_a_cms_edited_timeline_override(): void
    {
        $this->seed(TuringSeeder::class);

        $page = SpecialPage::where('slug', 'turing')->firstOrFail();
        $page->update([
            'content' => array_merge($page->content, [
                'timeline' => [
                    ['year' => '1999', 'title' => 'Evento CMS', 'text' => 'Testo.', 'details' => 'Approfondimento scritto da un redattore.'],
                ],
            ]),
        ]);

        $this->seed(TuringSeeder::class);

        $page->refresh();
        $this->assertSame(
            'Approfondimento scritto da un redattore.',
            $page->content['timeline'][0]['details']
        );
    }

    public function test_seeder_creates_an_active_page_with_the_expected_top_level_fields(): void
    {
        $this->seed(TuringSeeder::class);

        $page = SpecialPage::where('slug', 'turing')->first();

        $this->assertNotNull($page);
        $this->assertTrue($page->is_active);
        $this->assertSame('Alan Turing', $page->title);
        $this->assertNotEmpty($page->description);
    }

    public function test_seeder_populates_the_three_route_cards_with_the_canonical_urls(): void
    {
        $this->seed(TuringSeeder::class);

        $cards = SpecialPage::where('slug', 'turing')->firstOrFail()->content['cards'];

        $this->assertCount(3, $cards);

        $urls = array_column($cards, 'url');

        $this->assertSame(
            ['/turing/enigma', '/turing/ai', '/turing/legacy'],
            $urls
        );

        // La regressione storica (PR-T1): mai /turing/ia, mai un URL nullo.
        $this->assertNotContains('/turing/ia', $urls);
    }

    public function test_seeder_populates_all_four_editorial_blocks(): void
    {
        $this->seed(TuringSeeder::class);

        $blocks = SpecialPage::where('slug', 'turing')
            ->firstOrFail()
            ->content['editorial_blocks'];

        $this->assertCount(4, $blocks);
        $this->assertSame(
            ['enigma', 'macchina-universale', 'test-turing', 'ai-moderna'],
            array_column($blocks, 'key')
        );

        foreach ($blocks as $block) {
            $this->assertTrue($block['enabled']);
            $this->assertNotEmpty($block['title']);
            $this->assertNotEmpty($block['text']);
        }
    }

    public function test_seeder_does_not_set_a_timeline_override(): void
    {
        // Un campo 'timeline' non vuoto disattiverebbe la Timeline a capitoli
        // (Decision #003): il seeder deve lasciarlo assente cosi' il fallback
        // del controller continua a fornirla.
        $this->seed(TuringSeeder::class);

        $content = SpecialPage::where('slug', 'turing')->firstOrFail()->content;

        $this->assertArrayNotHasKey('timeline', $content);
    }

    public function test_the_full_database_seeder_wires_the_turing_page_automatically(): void
    {
        // Nessun intervento manuale richiesto dopo `php artisan migrate --seed`:
        // il DatabaseSeeder standard deve gia' includere TuringSeeder.
        $this->seed();

        $this->assertSame(1, SpecialPage::where('slug', 'turing')->count());
    }

    public function test_all_six_public_turing_pages_render_after_seeding(): void
    {
        $this->seed(TuringSeeder::class);

        $this->get(route('turing'))
            ->assertOk()
            ->assertSeeText('Alan Turing');

        $this->get(route('turing.enigma'))->assertOk();
        $this->get(route('turing.ai'))->assertOk();
        $this->get(route('turing.legacy'))->assertOk();
        $this->get(route('turing.computation'))->assertOk();
        $this->get(route('turing.intelligence'))->assertOk();
    }

    public function test_hub_page_shows_the_seeded_editorial_content(): void
    {
        $this->seed(TuringSeeder::class);

        $this->get(route('turing'))
            ->assertOk()
            ->assertSeeText('La guerra dei codici: Enigma e Bletchley Park')
            ->assertSeeText('Dai modelli linguistici alla nuova attualità di Turing');
    }

    public function test_hub_page_still_renders_the_chapter_based_timeline_after_seeding(): void
    {
        $this->seed(TuringSeeder::class);

        $this->get(route('turing'))
            ->assertOk()
            ->assertSee('timeline-chapter-opener-1', false);
    }

    // Verifica di non-regressione: le immagini hero/pannello di /turing/enigma
    // e /turing/ai devono restare identiche prima e dopo il seeding, perché il
    // seeder lascia volutamente assenti i campi image/background_image dei
    // blocchi editoriali (vedi commento in TuringSeeder).
    public function test_enigma_and_ai_detail_page_images_are_unchanged_by_seeding(): void
    {
        $enigmaBefore = $this->get(route('turing.enigma'))->getContent();
        $aiBefore = $this->get(route('turing.ai'))->getContent();

        $this->seed(TuringSeeder::class);

        $enigmaAfter = $this->get(route('turing.enigma'))->getContent();
        $aiAfter = $this->get(route('turing.ai'))->getContent();

        $this->assertSame(
            $this->extractBackgroundImageUrls($enigmaBefore),
            $this->extractBackgroundImageUrls($enigmaAfter)
        );

        $this->assertSame(
            $this->extractBackgroundImageUrls($aiBefore),
            $this->extractBackgroundImageUrls($aiAfter)
        );
    }

    private function extractBackgroundImageUrls(string $html): array
    {
        preg_match_all(
            '/background-image:url\(\'([^\']+)\'\)/',
            $html,
            $matches
        );

        return $matches[1];
    }

    public function test_admin_turing_editor_shows_seeded_content_instead_of_blank_fields(): void
    {
        $this->seed(TuringSeeder::class);

        $response = $this
            ->actingAs($this->editor())
            ->get(route('admin.turing'));

        $response->assertOk();

        $response->assertViewHas('page', function (SpecialPage $page) {
            return $page->content['hero']['title'] === 'Alan Turing'
                && $page->content['intro']['title']
                    === 'Dalla crittografia alla coscienza artificiale';
        });

        $response->assertSee(
            'Dalla crittografia alla coscienza artificiale',
            false
        );
    }
}
