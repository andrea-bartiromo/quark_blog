<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missione 35 (secondo batch autonomo KAIRUS, Fase E — Editorial Quality &
 * Readiness): "article completeness audit". Superficie HTTP per la vista
 * sitewide di EditorialQualityAuditService — l'unica precedentemente
 * disponibile solo via `php artisan content:quality-audit` (CLI) o per
 * singolo articolo nella pagina di modifica.
 */
class EditorialQualityAuditControllerTest extends TestCase
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
            'title' => 'Articolo di prova sufficientemente completo',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Un sommario abbastanza lungo da superare la soglia minima richiesta dal controllo.',
            'body' => '<p>'.str_repeat('Testo scientifico reale e sostanzioso. ', 15).'</p><a href="/articolo/altro">altro</a>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'cover_image' => 'cover.webp',
            'cover_alt' => 'Descrizione cover',
            'primary_sources' => 'https://example.com/fonte',
        ], $overrides));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.editorial-quality'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_page(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.editorial-quality'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_the_empty_state_when_no_articles_exist(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.editorial-quality'))
            ->assertOk()
            ->assertSee('Ogni articolo analizzato risulta Pronto.');
    }

    public function test_editor_sees_summary_counts_reflecting_real_articles(): void
    {
        $this->article();
        $incomplete = $this->article(['cover_image' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.editorial-quality'));

        $response->assertOk();
        $response->assertSee('Analizzati');
        $response->assertSee($incomplete->title);
        $response->assertSee('Da completare');
        $response->assertSee(route('admin.articles.edit', $incomplete));
    }

    public function test_a_ready_article_never_appears_in_the_flagged_list(): void
    {
        $ready = $this->article();

        $response = $this->actingAs($this->editor())->get(route('admin.editorial-quality'));

        $response->assertOk();
        $response->assertDontSee(route('admin.articles.edit', $ready));
    }

    public function test_status_filter_narrows_the_audit_to_the_selected_status(): void
    {
        $draft = $this->article(['title' => 'Bozza da filtrare', 'status' => Article::STATUS_DRAFT, 'published_at' => null, 'cover_image' => null]);
        $published = $this->article(['title' => 'Pubblicato da escludere', 'cover_image' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.editorial-quality', ['stato' => Article::STATUS_DRAFT]));

        $response->assertOk();
        $response->assertSee($draft->title);
        $response->assertDontSee($published->title);
    }

    public function test_an_invalid_status_value_is_ignored_rather_than_erroring(): void
    {
        $this->article(['cover_image' => null]);

        $this->actingAs($this->editor())
            ->get(route('admin.editorial-quality', ['stato' => 'non-uno-stato-valido']))
            ->assertOk();
    }

    /**
     * Missione 44 (secondo batch autonomo KAIRUS, Fase E — Editorial
     * Quality & Readiness): "quality drill-down". "Problemi più
     * frequenti" mostrava solo un elenco statico label+conteggio — mai un
     * modo di arrivare dagli articoli effettivi con quello specifico
     * problema senza scorrere l'intera tabella a mano. Il filtro
     * `problema` (stesso `code` machine-readable già calcolato da
     * EditorialQualityAuditService::mostFrequentIssues(), mai una nuova
     * classificazione) deve restringere la tabella a solo quegli
     * articoli.
     */
    public function test_the_issue_filter_narrows_the_flagged_table_to_only_matching_articles(): void
    {
        $noCover = $this->article(['title' => 'Senza copertina', 'cover_image' => null]);
        $noSources = $this->article(['title' => 'Senza fonti', 'primary_sources' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.editorial-quality', ['problema' => 'cover_present']));

        $response->assertOk();
        $response->assertSee('Filtrato per:');
        $response->assertSee($noCover->title);
        $response->assertDontSee($noSources->title);
    }

    public function test_an_unknown_issue_code_is_ignored_rather_than_erroring(): void
    {
        $flagged = $this->article(['cover_image' => null]);

        $response = $this->actingAs($this->editor())->get(route('admin.editorial-quality', ['problema' => 'non-un-codice-reale']));

        $response->assertOk();
        $response->assertDontSee('Filtrato per:');
        $response->assertSee($flagged->title);
    }

    public function test_the_page_performs_no_mutation_no_matter_how_many_times_it_is_viewed(): void
    {
        $editor = $this->editor();
        $article = $this->article(['cover_image' => null]);
        $before = $article->refresh()->getAttributes();

        $this->actingAs($editor)->get(route('admin.editorial-quality'));
        $this->actingAs($editor)->get(route('admin.editorial-quality'));

        $this->assertSame($before, Article::find($article->id)->getAttributes());
    }
}
