<?php

namespace Tests\Feature\Admin;

use App\Models\SpecialPage;
use App\Models\TuringChapterView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cantiere 63 (programma "100 cantieri Kairus", dipende dai Cantieri
 * 58-61): "Prototipo non pubblico navigazione Turing". Prima di questo
 * cantiere, finché `turing.chapters_public` è false (il default di
 * produzione), TuringPageController::index() mostrava a chiunque — un
 * editor autenticato incluso — solo la landing "In arrivo": nessuno
 * poteva rivedere l'hub reale né la rete di navigazione fra i 5 capitoli
 * prima di renderli pubblici.
 *
 * Stesso pattern già stabilito da ContentClusterAdminPreviewTest
 * (Cantiere 48) e CategoryAdminPreviewTest (Cantiere 11):
 * Admin\TuringController::previewHub()/previewChapter() riusano le
 * stesse viste pubbliche reali (mai una copia), sola lettura, dentro
 * auth+editor.
 */
class TuringNavigationPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const CHAPTERS = ['enigma', 'ai', 'legacy', 'computation', 'intelligence'];

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function seedTuringPage(): void
    {
        SpecialPage::create([
            'slug' => 'turing',
            'title' => 'Alan Turing',
            'description' => 'Speciale editoriale di prova.',
            'is_active' => true,
            'content' => [],
        ]);
    }

    public function test_editor_can_preview_the_hub_while_chapters_are_not_public(): void
    {
        config(['turing.chapters_public' => false]);
        $this->seedTuringPage();

        // La pagina pubblica reale mostra ancora la landing "In arrivo".
        $this->get(route('turing'))->assertOk()->assertSee('In lavorazione');

        $response = $this->actingAs($this->editor())->get(route('admin.turing.preview'));

        $response->assertOk();
        $response->assertSee('Anteprima amministrativa', false);
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    #[DataProvider('chapters')]
    public function test_editor_can_preview_each_chapter_while_it_is_not_public(string $chapter): void
    {
        config(['turing.chapters_public' => false]);
        $this->seedTuringPage();

        // La rotta pubblica reale del capitolo continua a reindirizzare.
        $this->get('/turing/'.$chapter)->assertRedirect(route('turing'));

        $response = $this->actingAs($this->editor())
            ->get(route('admin.turing.preview-chapter', $chapter));

        $response->assertOk();
        $response->assertSee('Anteprima amministrativa', false);
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }

    public static function chapters(): array
    {
        return array_map(fn (string $chapter) => [$chapter], self::CHAPTERS);
    }

    /**
     * Codex (PR #629, P2): finché il gate è chiuso, la rete di
     * navigazione propria delle pagine Turing (hero, card dell'hub,
     * blocchi editoriali, breadcrumb, CTA "Continua il percorso") deve
     * restare interamente dentro le rotte di anteprima — mai un link
     * reale verso /turing/*, che reindirizzerebbe l'editor fuori
     * dall'anteprima verso la landing "In arrivo"
     * (TuringPublicController::redirectToTuring()).
     *
     * L'header globale del sito (resources/views/components/header.blade.php)
     * mostra comunque una voce "Turing" verso la rotta pubblica reale —
     * chrome condiviso identico su ogni pagina del sito (articoli,
     * categorie, home), non specifico di questo cantiere né citato dal
     * finding Codex, quindi escluso qui esplicitamente dalla scansione.
     */
    public function test_no_page_in_the_preview_network_links_to_a_real_public_turing_route(): void
    {
        config(['turing.chapters_public' => false]);
        $this->seedTuringPage();
        $editor = $this->editor();

        $pages = ['hub' => $this->actingAs($editor)->get(route('admin.turing.preview'))->getContent()];

        foreach (self::CHAPTERS as $chapter) {
            $pages[$chapter] = $this->actingAs($editor)
                ->get(route('admin.turing.preview-chapter', $chapter))
                ->getContent();
        }

        foreach ($pages as $page => $html) {
            $withoutGlobalHeader = preg_replace('#<nav class="header-nav".*?</nav>#s', '', $html);

            $this->assertDoesNotMatchRegularExpression(
                '#href="(?:https?://[^"/]+)?/turing(/(enigma|ai|legacy|computation|intelligence))?"#',
                $withoutGlobalHeader,
                "la pagina '{$page}' dell'anteprima contiene un link verso una rotta pubblica reale di Turing."
            );
        }
    }

    public function test_guest_cannot_reach_the_hub_preview_route(): void
    {
        config(['turing.chapters_public' => false]);
        $this->seedTuringPage();

        $this->get(route('admin.turing.preview'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_reach_a_chapter_preview_route(): void
    {
        config(['turing.chapters_public' => false]);
        $this->seedTuringPage();

        $this->get(route('admin.turing.preview-chapter', 'enigma'))->assertRedirect(route('login'));
    }

    public function test_the_chapter_preview_route_rejects_an_unknown_chapter_name(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.turing.preview-chapter', 'hub'))
            ->assertNotFound();

        $this->actingAs($this->editor())
            ->get(route('admin.turing.preview-chapter', 'non-esiste'))
            ->assertNotFound();
    }

    public function test_public_hub_and_chapter_pages_are_unaffected_by_the_preview_routes_existing(): void
    {
        config(['turing.chapters_public' => true]);
        $this->seedTuringPage();

        $hubResponse = $this->get(route('turing'));
        $hubResponse->assertOk();
        $hubResponse->assertDontSee('Anteprima amministrativa', false);
        $hubResponse->assertDontSee('name="robots" content="noindex,nofollow"', false);

        $chapterResponse = $this->get(route('turing.enigma'));
        $chapterResponse->assertOk();
        $chapterResponse->assertDontSee('Anteprima amministrativa', false);
        $chapterResponse->assertDontSee('name="robots" content="noindex,nofollow"', false);
    }

    /**
     * L'anteprima non deve mai contaminare le metriche di navigazione
     * reali (App\Services\Turing\TuringNavigationMetricsService): stesso
     * principio già garantito lato pubblico dal marcatore
     * X-Kairus-Internal-Audit (Codex PR #623 P1), qui ottenuto più a
     * monte semplicemente non chiamando mai recordView() dal percorso di
     * anteprima.
     */
    public function test_previewing_the_hub_and_every_chapter_never_records_a_navigation_metric(): void
    {
        config(['turing.chapters_public' => false]);
        $this->seedTuringPage();
        $editor = $this->editor();

        $this->actingAs($editor)->get(route('admin.turing.preview'))->assertOk();

        foreach (self::CHAPTERS as $chapter) {
            $this->actingAs($editor)->get(route('admin.turing.preview-chapter', $chapter))->assertOk();
        }

        $this->assertSame(0, TuringChapterView::query()->count());
    }

    /**
     * "Vedi anteprima →" nell'editor admin: stesso pattern del link già
     * presente per Percorso (Cantiere 48) e Category (Cantiere 11) —
     * senza questo link, la route esisterebbe ma resterebbe
     * scopribile solo digitando l'URL a mano.
     */
    public function test_the_admin_editor_links_to_the_hub_preview(): void
    {
        $this->seedTuringPage();

        $this->actingAs($this->editor())
            ->get(route('admin.turing'))
            ->assertOk()
            ->assertSee(route('admin.turing.preview'), false);
    }
}
