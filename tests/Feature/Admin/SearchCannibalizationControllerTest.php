<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 5 (programma "Kairus Organic Discovery"): rilevatore di
 * cannibalizzazione di ricerca — sola lettura.
 */
class SearchCannibalizationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function publicArticle(string $slug): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo '.$slug,
            'slug' => $slug,
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_guest_cannot_view_the_page(): void
    {
        $this->get(route('admin.search-cannibalization'))->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_view_the_page(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)->get(route('admin.search-cannibalization'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_sees_empty_state_without_imports(): void
    {
        $this->actingAs($this->editor())->get(route('admin.search-cannibalization'))
            ->assertOk()->assertSee('Nessun import Search Console disponibile.');
    }

    public function test_editor_sees_no_finding_when_a_query_has_a_single_matched_article(): void
    {
        $article = $this->publicArticle('unico');
        SearchConsoleQuery::create([
            'query' => 'query unica', 'page_url' => 'https://kairus.it/articolo/unico', 'article_id' => $article->id,
            'clicks' => 2, 'impressions' => 50, 'ctr' => 0.04, 'position' => 5.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);

        $this->actingAs($this->editor())->get(route('admin.search-cannibalization'))
            ->assertOk()->assertSee('Nessuna cannibalizzazione rilevata');
    }

    public function test_editor_sees_a_finding_when_two_public_articles_compete_for_the_same_query(): void
    {
        $primary = $this->publicArticle('primario');
        $secondary = $this->publicArticle('secondario');

        SearchConsoleQuery::create([
            'query' => 'query in competizione', 'page_url' => 'https://kairus.it/articolo/primario', 'article_id' => $primary->id,
            'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 4.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);
        SearchConsoleQuery::create([
            'query' => 'query in competizione', 'page_url' => 'https://kairus.it/articolo/secondario', 'article_id' => $secondary->id,
            'clicks' => 1, 'impressions' => 15, 'ctr' => 0.066, 'position' => 8.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);

        $this->actingAs($this->editor())->get(route('admin.search-cannibalization'))
            ->assertOk()
            ->assertSee('query in competizione')
            ->assertSee('Articolo primario')
            ->assertSee('Articolo secondario')
            ->assertSee('probabile primario');
    }

    public function test_a_draft_competing_article_is_never_shown_as_a_finding(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $public = $this->publicArticle('pubblico-bozza');
        $draft = Article::create([
            'user_id' => $author->id, 'title' => 'Bozza', 'slug' => 'bozza-cannibalizzazione',
            'body' => 'Corpo.', 'category' => 'spazio', 'status' => Article::STATUS_DRAFT, 'published_at' => null,
        ]);

        SearchConsoleQuery::create([
            'query' => 'query con bozza', 'page_url' => 'https://kairus.it/articolo/pubblico-bozza', 'article_id' => $public->id,
            'clicks' => 2, 'impressions' => 30, 'ctr' => 0.066, 'position' => 5.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);
        SearchConsoleQuery::create([
            'query' => 'query con bozza', 'page_url' => 'https://kairus.it/articolo/bozza-cannibalizzazione', 'article_id' => $draft->id,
            'clicks' => 1, 'impressions' => 30, 'ctr' => 0.033, 'position' => 6.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);

        $this->actingAs($this->editor())->get(route('admin.search-cannibalization'))
            ->assertOk()->assertSee('Nessuna cannibalizzazione rilevata');
    }

    public function test_the_merge_decision_form_posts_to_the_existing_search_opportunities_route(): void
    {
        $primary = $this->publicArticle('form-primario');
        $secondary = $this->publicArticle('form-secondario');

        SearchConsoleQuery::create([
            'query' => 'query form', 'page_url' => 'https://kairus.it/articolo/form-primario', 'article_id' => $primary->id,
            'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 4.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);
        SearchConsoleQuery::create([
            'query' => 'query form', 'page_url' => 'https://kairus.it/articolo/form-secondario', 'article_id' => $secondary->id,
            'clicks' => 1, 'impressions' => 15, 'ctr' => 0.066, 'position' => 8.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);

        $this->actingAs($this->editor())->get(route('admin.search-cannibalization'))
            ->assertOk()
            ->assertSee(route('admin.search-opportunities.record-decision'), false)
            ->assertSee('search_cannibalization|query form|https://kairus.it/articolo/form-primario', false);
    }
}
