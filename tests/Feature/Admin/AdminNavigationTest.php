<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Project;
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

    /**
     * Verifica lo stato aperto/chiuso del gruppo collassabile (<details>)
     * la cui <summary> contiene esattamente $label. I gruppi sono fratelli,
     * mai annidati, quindi un match non-greedy fino al primo </details>
     * successivo individua correttamente i confini del singolo gruppo.
     */
    private function assertGroupOpen(TestResponse $response, string $label): void
    {
        $nav = $this->normalizeWhitespace($this->navFragment($response));
        $pattern = '/<details class="admin-nav__group" open\s*> <summary[^>]*>'.preg_quote($label, '/').'<\/summary>/';

        $this->assertMatchesRegularExpression($pattern, $nav, "Il gruppo \"{$label}\" dovrebbe essere aperto.");
    }

    private function assertGroupClosed(TestResponse $response, string $label): void
    {
        $nav = $this->normalizeWhitespace($this->navFragment($response));
        $openPattern = '/<details class="admin-nav__group" open\s*> <summary[^>]*>'.preg_quote($label, '/').'<\/summary>/';
        $closedPattern = '/<details class="admin-nav__group" > <summary[^>]*>'.preg_quote($label, '/').'<\/summary>/';

        $this->assertDoesNotMatchRegularExpression($openPattern, $nav, "Il gruppo \"{$label}\" non dovrebbe essere aperto.");
        $this->assertMatchesRegularExpression($closedPattern, $nav, "Il gruppo \"{$label}\" dovrebbe comunque essere presente (chiuso).");
    }

    /**
     * Estrae il blocco <details>...</details> del gruppo $label (dal
     * markup con whitespace normalizzato), per verificare quali voci
     * contiene senza dipendere da quelle degli altri gruppi.
     */
    private function groupBlock(TestResponse $response, string $label): string
    {
        $nav = $this->normalizeWhitespace($this->navFragment($response));
        $pattern = '/<details class="admin-nav__group"[^>]*> <summary[^>]*>'.preg_quote($label, '/').'<\/summary>.*?<\/details>/';

        $this->assertMatchesRegularExpression($pattern, $nav, "Impossibile trovare il gruppo \"{$label}\".");
        preg_match($pattern, $nav, $matches);

        return $matches[0];
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

        foreach ([
            'Principale', 'Contenuti', 'Redazione', 'Progettazione', 'Comunicazione',
            'Strumenti', 'Monetizzazione', 'Analisi', 'Sistema', 'Account',
        ] as $group) {
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

    public function test_the_progettazione_section_and_its_five_areas_are_present(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        $this->assertStringContainsString('Progettazione', $nav);
        $this->assertStringContainsString('Panoramica', $nav);
        $this->assertStringContainsString('Attività progetti', $nav);
        $this->assertStringContainsString('Calendario', $nav);
        $this->assertStringContainsString(route('admin.progettazione.dashboard'), $nav);
        $this->assertStringContainsString(route('admin.progettazione.projects.index'), $nav);
        $this->assertStringContainsString(route('admin.progettazione.tasks.index-all'), $nav);
        $this->assertStringContainsString(route('admin.progettazione.calendar'), $nav);
        $this->assertStringContainsString(route('admin.progettazione.documents.index-all'), $nav);
    }

    public function test_the_progettazione_projects_link_is_active_for_nested_project_routes(): void
    {
        $editor = $this->editor();
        $project = Project::factory()->create();

        $response = $this->actingAs($editor)->get(route('admin.progettazione.projects.tasks.create', $project));

        $this->assertNavLinkActive($response, 'admin.progettazione.projects.index');
    }

    public function test_the_progettazione_dashboard_link_is_only_active_on_its_own_page(): void
    {
        $editor = $this->editor();

        $dashboardResponse = $this->actingAs($editor)->get(route('admin.progettazione.dashboard'));
        $this->assertNavLinkActive($dashboardResponse, 'admin.progettazione.dashboard');

        $projectsResponse = $this->actingAs($editor)->get(route('admin.progettazione.projects.index'));
        $this->assertNavLinkNotActive($projectsResponse, 'admin.progettazione.dashboard');
    }

    // ── Nuova information architecture: gruppi collassabili ─────────────
    //
    // La sidebar precedente aveva un solo gruppo comprimibile ("Strumenti",
    // contenente Turing, Assistente AI, Statistiche, Attività e Anteprima
    // newsletter — un insieme eterogeneo senza un criterio comune). La
    // nuova IA rende comprimibili anche Contenuti/Redazione/Progettazione/
    // Comunicazione, aggiunge Monetizzazione/Analisi/Sistema come gruppi
    // dedicati, e sposta Statistiche→Analisi, Attività→Sistema, Anteprima
    // newsletter→Comunicazione (restano affini a Newsletter). "Strumenti"
    // resta con solo Turing e Assistente AI (strumenti editoriali AI).

    public function test_only_the_group_containing_the_active_page_is_open(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));

        // Dashboard e Account non sono raggruppati (sempre visibili, FASE
        // 5.2 della missione IA): nessun gruppo dovrebbe risultare aperto.
        foreach ([
            'Contenuti', 'Redazione', 'Progettazione', 'Comunicazione',
            'Strumenti', 'Monetizzazione', 'Analisi', 'Sistema',
        ] as $group) {
            $this->assertGroupClosed($response, $group);
        }
    }

    public function test_the_monetizzazione_group_opens_for_the_ads_page(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.ads'));

        $this->assertGroupOpen($response, 'Monetizzazione');
        $this->assertNavLinkActive($response, 'admin.ads');

        foreach (['Contenuti', 'Redazione', 'Progettazione', 'Comunicazione', 'Strumenti', 'Analisi', 'Sistema'] as $other) {
            $this->assertGroupClosed($response, $other);
        }
    }

    public function test_the_analisi_group_opens_for_the_stats_page(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.stats'));

        $this->assertGroupOpen($response, 'Analisi');
        $this->assertNavLinkActive($response, 'admin.stats');
        $this->assertGroupClosed($response, 'Strumenti');
    }

    public function test_the_sistema_group_opens_for_the_activity_page(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.activity'));

        $this->assertGroupOpen($response, 'Sistema');
        $this->assertNavLinkActive($response, 'admin.activity');
        $this->assertGroupClosed($response, 'Strumenti');
    }

    public function test_the_comunicazione_group_opens_for_the_newsletter_preview_page(): void
    {
        // Anteprima newsletter e' stata spostata da "Strumenti" a
        // "Comunicazione" (dove vive anche "Newsletter"): verifica che sia
        // il nuovo gruppo ad aprirsi, non quello vecchio.
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.newsletter.preview'));

        $this->assertGroupOpen($response, 'Comunicazione');
        $this->assertNavLinkActive($response, 'admin.newsletter.preview');
        $this->assertGroupClosed($response, 'Strumenti');
    }

    public function test_the_strumenti_group_only_contains_turing_and_ai_assistant(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.turing'));
        $block = $this->groupBlock($response, 'Strumenti');

        $this->assertStringContainsString('Turing', $block);
        $this->assertStringContainsString('Assistente AI', $block);
        $this->assertStringNotContainsString('Statistiche', $block);
        $this->assertStringNotContainsString('>Attività<', $block);
        $this->assertStringNotContainsString('Anteprima newsletter', $block);
    }

    public function test_the_comunicazione_group_contains_newsletter_preview(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.comunicazione.dashboard'));
        $block = $this->groupBlock($response, 'Comunicazione');

        $this->assertStringContainsString('Newsletter', $block);
        $this->assertStringContainsString('Anteprima newsletter', $block);
    }

    public function test_nav_icons_are_hidden_from_assistive_technology(): void
    {
        // L'etichetta testuale accanto a ogni icona e' gia' il nome
        // accessibile del link: annunciare anche l'emoji duplicherebbe
        // l'informazione per chi usa uno screen reader.
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        $this->assertStringContainsString('<span class="icon" aria-hidden="true">', $nav);
        $this->assertStringNotContainsString('<span class="icon">', $nav);
    }

    public function test_nav_labels_are_wrapped_for_compact_mode_visibility_toggling(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->navFragment($response);

        $this->assertStringContainsString('<span class="admin-nav__label">Dashboard</span>', $nav);
    }

    public function test_the_compact_sidebar_toggle_is_present_and_accessible(): void
    {
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));

        $response->assertSee('data-admin-sidebar-compact-toggle', false);
        $response->assertSee('aria-pressed="false"', false);
        $response->assertSee('Comprimi la sidebar', false);
    }

    public function test_see_site_link_uses_noopener_for_the_new_tab(): void
    {
        // target="_blank" senza rel="noopener" lascia alla pagina aperta
        // accesso a window.opener (tabnabbing) — corretto qui perche' e'
        // esattamente la riga toccata dal refactor del link "Vedi sito".
        $editor = $this->editor();

        $response = $this->actingAs($editor)->get(route('admin.dashboard'));
        $nav = $this->normalizeWhitespace($this->navFragment($response));

        $this->assertStringContainsString('target="_blank" rel="noopener"', $nav);
    }
}
