<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\ArticleSlugRedirect;
use App\Models\User;
use App\Services\InternalLinking\InternalLinkAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 42 (secondo batch autonomo KAIRUS, Fase E — Editorial Quality &
 * Readiness): "orphan article detection". Superficie HTTP per
 * InternalLinkAuditService — l'unica precedentemente disponibile solo via
 * `php artisan content:internal-link-audit` (CLI).
 */
class InternalLinkAuditControllerTest extends TestCase
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

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Un breve sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.internal-link-audit'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_page(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.internal-link-audit'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_empty_state_when_no_articles_exist(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.internal-link-audit'))
            ->assertOk()
            ->assertSee('Nessuno — tutti gli articoli analizzati sono senza anomalie rilevate.');
    }

    /**
     * L'articolo isolato è la definizione corretta di "orfano" (pubblicato,
     * zero incoming links) — deve comparire sia nella sezione dedicata sia
     * nella tabella delle anomalie della pagina reale, non solo nel report
     * del servizio.
     */
    public function test_editor_sees_an_isolated_published_article_in_both_sections(): void
    {
        $orphan = $this->article(['title' => 'Articolo isolato dashboard']);

        $response = $this->actingAs($this->editor())->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertSee('Pubblicati senza incoming links');
        $response->assertSee('Articolo isolato dashboard');
        $response->assertSee(route('admin.articles.edit', $orphan));
        $response->assertSee('isolato');
    }

    /**
     * Missione 43 (secondo batch autonomo KAIRUS, Fase E — Editorial
     * Quality & Readiness): "broken relationship safeguards" — un link
     * reale già presente nel body pubblicato di un articolo verso un
     * articolo poi eliminato deve comparire davvero, come "link rotti",
     * nella pagina reale — non solo nel report del servizio (già provato
     * dal test di servizio equivalente in InternalLinkAuditCommandTest).
     */
    public function test_editor_sees_a_broken_link_row_after_the_target_article_is_deleted(): void
    {
        $target = $this->article(['title' => 'Articolo da eliminare dashboard', 'slug' => 'articolo-da-eliminare-dashboard']);
        $source = $this->article([
            'title' => 'Articolo con link rotto dashboard',
            'body' => '<p><a href="/articolo/articolo-da-eliminare-dashboard">vedi</a></p>',
        ]);
        $target->delete();

        $response = $this->actingAs($this->editor())->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertSee('Articolo con link rotto dashboard');
        $response->assertSee(route('admin.articles.edit', $source));
        $response->assertSee('1 link rotti');
    }

    public function test_a_published_article_with_an_incoming_link_is_never_reported_as_isolated(): void
    {
        $target = $this->article(['title' => 'Articolo collegato dashboard', 'slug' => 'articolo-collegato-dashboard']);
        $this->article(['body' => '<p><a href="/articolo/articolo-collegato-dashboard">vedi</a></p>']);

        $response = $this->actingAs($this->editor())->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertDontSee($target->title);
        $response->assertDontSee(route('admin.articles.edit', $target));
    }

    public function test_a_draft_article_with_zero_incoming_links_is_never_reported_as_isolated(): void
    {
        $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertSee('Pubblicati senza incoming links');
        $response->assertSee('Nessuno — ogni articolo pubblicato riceve almeno un collegamento interno.');
    }

    /**
     * Missione 47 (secondo batch autonomo KAIRUS, Fase F — Search
     * Intelligence): InternalLinkAuditService già calcola
     * scheduledWithoutInternalLinks e highConfidenceUnusedSuggestions (vedi
     * InternalLinkAuditCommandTest per la prova a livello di servizio), ma
     * la pagina reale non li mostrava mai — restavano dati morti.
     */
    public function test_editor_sees_a_scheduled_article_without_internal_links(): void
    {
        $scheduled = $this->article([
            'title' => 'Articolo programmato senza link',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertSee('Programmati senza link interni');
        $response->assertSee('Articolo programmato senza link');
        $response->assertSee(route('admin.articles.edit', $scheduled));
    }

    public function test_editor_sees_a_high_confidence_unused_suggestion(): void
    {
        $source = $this->article(['title' => 'Sorgente suggerimento']);
        $target = $this->article(['title' => 'Destinazione suggerimento']);

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine specifico',
            'reason' => 'motivo',
            'confidence_score' => 85,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertSee('Opportunità di collegamento ad alta confidenza');
        $response->assertSee('Sorgente suggerimento');
        $response->assertSee('Destinazione suggerimento');
        $response->assertSee('termine specifico');
        $response->assertSee('85');
    }

    public function test_neither_new_section_appears_when_nothing_qualifies(): void
    {
        $this->article();

        $response = $this->actingAs($this->editor())->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertDontSee('Programmati senza link interni');
        $response->assertDontSee('Opportunità di collegamento ad alta confidenza');
    }

    /**
     * Missione 51 (secondo batch autonomo KAIRUS, Fase F — Search
     * Intelligence): InternalLinkAuditCommand (CLI) stampa già tutti e
     * undici i campi del report nella sezione "ARTICOLI", ma la pagina
     * reale ne mostrava solo 4 di 11 — withoutOutgoingLinks,
     * withOneOutgoingLink, withTwoOrMoreOutgoingLinks, selfLinks,
     * unpublishedTargets, scheduledSafeLinks e redirectedLinks restavano
     * calcolati ma mai visibili fuori dalla CLI. Il servizio è già
     * usato qui come oracolo dei numeri attesi (stesso principio
     * "aggregazione, mai una regola di dominio" già applicato altrove in
     * questo file) — le classificazioni stesse sono già esaustivamente
     * provate da InternalLinkAuditCommandTest.
     */
    public function test_editor_sees_all_seven_previously_hidden_articles_stat_cards_rendered_on_the_page(): void
    {
        $editor = $this->editor();

        // Senza link interni.
        $this->article(['title' => 'Senza link mission51']);

        // Con 1 link (valido).
        $target = $this->article(['title' => 'Target valido mission51', 'slug' => 'target-valido-mission51']);
        $this->article(['body' => '<p><a href="/articolo/target-valido-mission51">vedi</a></p>']);

        // Self-link.
        $selfLinker = $this->article(['slug' => 'si-collega-da-solo-mission51']);
        $selfLinker->update(['body' => '<p><a href="/articolo/si-collega-da-solo-mission51">questo stesso articolo</a></p>']);

        // Link reindirizzato.
        $redirectTarget = $this->article(['title' => 'Target redirect mission51', 'slug' => 'nuovo-slug-mission51']);
        ArticleSlugRedirect::create(['old_slug' => 'vecchio-slug-mission51', 'article_id' => $redirectTarget->id]);
        $this->article(['body' => '<p><a href="/articolo/vecchio-slug-mission51">vedi</a></p>']);

        $report = app(InternalLinkAuditService::class)->audit();

        $response = $this->actingAs($editor)->get(route('admin.internal-link-audit'));

        $response->assertOk();
        $response->assertSee('Senza link interni');
        $response->assertSee('Con 1 link');
        $response->assertSee('Con 2+ link');
        $response->assertSee('Self-link');
        $response->assertSee('Target non pubblicati');
        $response->assertSee('Target scheduled sicuri');
        $response->assertSee('Link reindirizzati');
        $response->assertSee(number_format($report->withoutOutgoingLinks));
        $response->assertSee(number_format($report->withOneOutgoingLink));
        $response->assertSee(number_format($report->withTwoOrMoreOutgoingLinks));
        $response->assertSee(number_format($report->selfLinks));
        $response->assertSee(number_format($report->unpublishedTargets));
        $response->assertSee(number_format($report->scheduledSafeLinks));
        $response->assertSee(number_format($report->redirectedLinks));
        $this->assertGreaterThan(0, $report->withoutOutgoingLinks);
        $this->assertGreaterThan(0, $report->withOneOutgoingLink);
        $this->assertSame(1, $report->selfLinks);
        $this->assertSame(1, $report->redirectedLinks);
    }

    public function test_the_page_performs_no_mutation_no_matter_how_many_times_it_is_viewed(): void
    {
        $editor = $this->editor();
        $article = $this->article();
        $before = $article->refresh()->getAttributes();

        $this->actingAs($editor)->get(route('admin.internal-link-audit'));
        $this->actingAs($editor)->get(route('admin.internal-link-audit'));

        $this->assertSame($before, Article::find($article->id)->getAttributes());
    }
}
