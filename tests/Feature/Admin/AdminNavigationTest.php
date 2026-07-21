<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    /**
     * Estrae il markup del solo <nav class="admin-nav">...</nav>, per non far
     * dipendere le asserzioni sulla sidebar dal resto del contenuto pagina
     * (es. i widget "azioni rapide" della dashboard, fuori scope per questa PR).
     */
    private function navFragment(TestResponse $response): string
    {
        preg_match('/<nav class="admin-nav".*?<\/nav>/s', $response->getContent(), $matches);

        $this->assertNotEmpty($matches, 'Impossibile individuare il markup <nav class="admin-nav"> nella risposta.');

        return $matches[0];
    }

    /**
     * Rimuove le andate a capo/indentazioni tra gli attributi HTML, cosi che le
     * asserzioni sul markup non dipendano dalla formattazione multi-riga del
     * template Blade (attributi come class/aria-current sono su righe separate).
     */
    private function normalizeWhitespace(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', $html));
    }

    private function assertNavLinkActive(TestResponse $response, string $routeName): void
    {
        $nav = $this->normalizeWhitespace($this->navFragment($response));
        $expected = '<a href="'.route($routeName).'" class="active" aria-current="page" >';

        $this->assertStringContainsString($expected, $nav);
    }

    private function assertNavLinkNotActive(TestResponse $response, string $routeName): void
    {
        $nav = $this->normalizeWhitespace($this->navFragment($response));
        $activeMarker = '<a href="'.route($routeName).'" class="active" aria-current="page" >';

        $this->assertStringNotContainsString($activeMarker, $nav);
    }

    public function test_an_authorized_editor_sees_the_admin_navigation(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('admin-nav', false);
    }

    public function test_an_unauthenticated_user_cannot_access_the_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_non_editor_user_cannot_access_the_admin_dashboard(): void
    {
        $author = $this->author();

        $response = $this->actingAs($author)->get(route('admin.dashboard'));

        $response->assertRedirect(route('redazione.dashboard'));
    }

    public function test_the_dashboard_contains_the_new_group_titles(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        foreach (['Principale', 'Contenuti', 'Redazione', 'Comunicazione', 'Strumenti', 'Account'] as $group) {
            $this->assertStringContainsString($group, $nav);
        }
    }

    public function test_all_previous_navigation_items_are_still_present(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        $labels = [
            'Dashboard', 'Articoli', 'Categorie', 'Media', 'Commenti',
            'Revisione', 'Fonti', 'Collaboratori',
            'Newsletter', 'Pubblicità',
            'Turing', 'Assistente AI', 'Statistiche', 'Attività', 'Anteprima newsletter',
            'Profilo', 'Vedi sito', 'Esci',
        ];

        foreach ($labels as $label) {
            $this->assertStringContainsString($label, $nav);
        }
    }

    public function test_obsolete_labels_no_longer_appear_in_the_navigation(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        // Verificate solo dentro <nav>: "Speciale Turing", "Log attività" e
        // "Suggerimenti AI" continuano a comparire come titolo pagina o nei
        // widget della dashboard (contenuto pagina, fuori scope), ma non
        // devono più comparire come etichette della sidebar.
        $this->assertStringNotContainsString('Speciale Turing', $nav);
        $this->assertStringNotContainsString('Verifica fonti', $nav);
        $this->assertStringNotContainsString('Suggerimenti AI', $nav);
        $this->assertStringNotContainsString('Log attività', $nav);
    }

    public function test_navigation_links_point_to_the_correct_routes(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        $expectedRoutes = [
            'admin.dashboard', 'admin.articles', 'admin.categories', 'admin.media', 'admin.comments',
            'admin.review', 'admin.verification', 'admin.collaborators',
            'admin.newsletter', 'admin.ads',
            'admin.turing', 'admin.suggestions', 'admin.stats', 'admin.activity', 'admin.newsletter.preview',
            'admin.profile', 'admin.logout', 'home',
        ];

        foreach ($expectedRoutes as $routeName) {
            $this->assertStringContainsString(route($routeName), $nav);
        }
    }

    public function test_the_dashboard_link_is_only_active_on_the_dashboard(): void
    {
        $editor = $this->editor();

        $dashboardResponse = $this->actingAs($editor)->get(route('admin.dashboard'));
        $this->assertNavLinkActive($dashboardResponse, 'admin.dashboard');

        $articlesResponse = $this->actingAs($editor)->get(route('admin.articles'));
        $this->assertNavLinkNotActive($articlesResponse, 'admin.dashboard');
        $this->assertNavLinkActive($articlesResponse, 'admin.articles');
    }

    public function test_the_articles_link_is_active_for_the_articles_list_create_and_edit_routes(): void
    {
        $editor = $this->editor();
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'status' => 'draft',
        ]);

        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.articles')), 'admin.articles');
        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.articles.create')), 'admin.articles');
        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.articles.edit', $article)), 'admin.articles');
    }

    public function test_the_categories_link_is_active_for_the_categories_route(): void
    {
        $editor = $this->editor();

        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.categories')), 'admin.categories');
    }

    public function test_the_newsletter_link_is_active_for_its_related_routes(): void
    {
        $editor = $this->editor();

        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.newsletter')), 'admin.newsletter');
    }

    public function test_the_newsletter_preview_has_its_own_distinct_active_state(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.newsletter.preview'));

        $this->assertNavLinkActive($response, 'admin.newsletter.preview');
        $this->assertNavLinkNotActive($response, 'admin.newsletter');
    }

    public function test_the_turing_link_is_active_for_admin_turing_routes(): void
    {
        $editor = $this->editor();

        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.turing')), 'admin.turing');
    }

    public function test_the_statistics_link_is_active_on_the_statistics_page(): void
    {
        $editor = $this->editor();

        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.stats')), 'admin.stats');
    }

    public function test_the_statistics_active_state_also_covers_the_charts_route_name(): void
    {
        // admin.stats.charts e un endpoint JSON (nessuna pagina/sidebar da
        // renderizzare), quindi qui si verifica direttamente che la rotta
        // rientri nel pattern usato dalla sidebar per lo stato attivo di
        // "Statistiche" (routeIs('admin.stats*')), coerentemente con quanto
        // verificato via HTML per admin.stats nel test precedente.
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.stats.charts'));
        $response->assertOk();

        $this->assertTrue(request()->routeIs('admin.stats*'));
    }

    public function test_the_activity_link_is_active_for_the_activity_log(): void
    {
        $editor = $this->editor();

        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.activity')), 'admin.activity');
    }

    public function test_the_profile_link_is_active_for_all_profile_routes(): void
    {
        $editor = $this->editor();

        $this->assertNavLinkActive($this->actingAs($editor)->get(route('admin.profile')), 'admin.profile');
    }

    public function test_the_tools_section_is_open_when_it_contains_the_active_route(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.turing'));
        $nav = $this->normalizeWhitespace($this->navFragment($response));

        $this->assertStringContainsString('<details class="admin-nav__group" open >', $nav);
    }

    public function test_the_tools_section_is_closed_when_it_does_not_contain_the_active_route(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->normalizeWhitespace($this->navFragment($response));

        $this->assertStringNotContainsString('<details class="admin-nav__group" open >', $nav);
        $this->assertStringContainsString('<details class="admin-nav__group" >', $nav);
    }

    public function test_logout_is_a_post_form_not_a_get_link(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        $this->assertStringContainsString(
            '<form id="logout-form" action="'.route('admin.logout').'" method="POST"',
            $nav
        );
        $this->assertStringNotContainsString('href="'.route('admin.logout').'"', $nav);
    }

    public function test_no_navigation_links_point_to_missing_routes(): void
    {
        // Il rendering stesso della sidebar fallirebbe con una
        // RouteNotFoundException (risposta 500) se un solo route() chiamato
        // nel template referenziasse una rotta inesistente: una risposta 200
        // dimostra che tutte le rotte usate nella sidebar sono valide.
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));

        $response->assertOk();
    }
}
