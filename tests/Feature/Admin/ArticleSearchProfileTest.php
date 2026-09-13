<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\ArticleSearchProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 2 (programma "Kairus Organic Discovery"). Profilo di ricerca
 * editoriale: facoltativo, 1:1 per articolo, mai letto da alcuna pagina
 * pubblica — solo la redazione lo vede/modifica.
 */
class ArticleSearchProfileTest extends TestCase
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
        $author = User::factory()->create(['role' => 'author']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Le api sono insetti sociali',
            'slug' => 'api-insetti-sociali-'.uniqid(),
            'excerpt' => 'Un sommario di prova.',
            'body' => 'Corpo articolo di prova.',
            'category' => 'natura',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'primary_intent' => 'Capire cosa sono le api e come vivono',
            'primary_query' => 'ape insetto',
            'secondary_queries' => "differenza tra ape e vespa\nquanto vive un'ape",
            'reader_questions' => "Le api pungono sempre?\nPerché le api sono importanti?",
            'content_type' => ArticleSearchProfile::CONTENT_TYPE_EXPLANATION,
            'reader_level' => ArticleSearchProfile::READER_LEVEL_BEGINNER,
            'last_editorial_review_at' => now()->toDateString(),
            'freshness_note' => 'Aggiornato con le fonti più recenti.',
            'evidence_scope' => 'Copre solo le api europee, non tutte le specie di Apis.',
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $article = $this->article();

        $this->put(route('admin.articles.search-profile.update', $article), $this->validPayload())
            ->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_save_a_search_profile(): void
    {
        $article = $this->article();

        $this->actingAs($this->author())
            ->put(route('admin.articles.search-profile.update', $article), $this->validPayload())
            ->assertRedirect(route('redazione.dashboard'));

        $this->assertSame(0, ArticleSearchProfile::query()->count());
    }

    public function test_editor_can_save_a_full_search_profile(): void
    {
        $article = $this->article();

        $response = $this->actingAs($this->editor())
            ->put(route('admin.articles.search-profile.update', $article), $this->validPayload());

        $response->assertRedirect(route('admin.articles.edit', $article));

        $profile = ArticleSearchProfile::query()->where('article_id', $article->id)->firstOrFail();
        $this->assertSame('Capire cosa sono le api e come vivono', $profile->primary_intent);
        $this->assertSame('ape insetto', $profile->primary_query);
        $this->assertSame(['differenza tra ape e vespa', "quanto vive un'ape"], $profile->secondary_queries);
        $this->assertSame(['Le api pungono sempre?', 'Perché le api sono importanti?'], $profile->reader_questions);
        $this->assertSame(ArticleSearchProfile::CONTENT_TYPE_EXPLANATION, $profile->content_type);
        $this->assertSame(ArticleSearchProfile::READER_LEVEL_BEGINNER, $profile->reader_level);
        $this->assertSame(now()->toDateString(), $profile->last_editorial_review_at->toDateString());
    }

    public function test_saving_again_updates_the_same_row_instead_of_duplicating(): void
    {
        $article = $this->article();
        $editor = $this->editor();

        $this->actingAs($editor)->put(route('admin.articles.search-profile.update', $article), $this->validPayload());
        $this->actingAs($editor)->put(
            route('admin.articles.search-profile.update', $article),
            $this->validPayload(['primary_query' => 'nuova query primaria'])
        );

        $this->assertSame(1, ArticleSearchProfile::query()->where('article_id', $article->id)->count());
        $this->assertSame('nuova query primaria', ArticleSearchProfile::query()->first()->primary_query);
    }

    public function test_blank_lines_are_filtered_out_of_secondary_queries_and_reader_questions(): void
    {
        $article = $this->article();

        $this->actingAs($this->editor())->put(route('admin.articles.search-profile.update', $article), $this->validPayload([
            'secondary_queries' => "prima query\n\n   \nseconda query\n",
            'reader_questions' => '',
        ]));

        $profile = ArticleSearchProfile::query()->firstOrFail();
        $this->assertSame(['prima query', 'seconda query'], $profile->secondary_queries);
        $this->assertSame([], $profile->reader_questions);
    }

    public function test_more_than_ten_secondary_queries_are_rejected(): void
    {
        $article = $this->article();
        $tooMany = implode("\n", array_map(fn ($i) => "query numero {$i}", range(1, 11)));

        $this->actingAs($this->editor())
            ->put(route('admin.articles.search-profile.update', $article), $this->validPayload(['secondary_queries' => $tooMany]))
            ->assertSessionHasErrors('secondary_queries');

        $this->assertSame(0, ArticleSearchProfile::query()->count());
    }

    public function test_an_invalid_content_type_is_rejected(): void
    {
        $article = $this->article();

        $this->actingAs($this->editor())
            ->put(route('admin.articles.search-profile.update', $article), $this->validPayload(['content_type' => 'not-a-real-type']))
            ->assertSessionHasErrors('content_type');
    }

    public function test_a_future_last_editorial_review_date_is_rejected(): void
    {
        $article = $this->article();

        $this->actingAs($this->editor())
            ->put(route('admin.articles.search-profile.update', $article), $this->validPayload([
                'last_editorial_review_at' => now()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('last_editorial_review_at');
    }

    public function test_editing_an_article_shows_a_collision_warning_when_another_article_shares_the_normalized_primary_query(): void
    {
        $articleOne = $this->article(['title' => 'Articolo uno']);
        $articleTwo = $this->article(['title' => 'Articolo due']);
        $editor = $this->editor();

        $this->actingAs($editor)->put(route('admin.articles.search-profile.update', $articleOne), $this->validPayload([
            'primary_query' => 'Ape Insetto',
        ]));
        $this->actingAs($editor)->put(route('admin.articles.search-profile.update', $articleTwo), $this->validPayload([
            'primary_query' => '  ape   insetto  ',
        ]));

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $articleTwo));

        $response->assertOk();
        $response->assertSee('Stessa query primaria anche in', false);
        $response->assertSee('Articolo uno');
    }

    public function test_no_collision_warning_when_no_other_article_shares_the_primary_query(): void
    {
        $article = $this->article();
        $editor = $this->editor();

        $this->actingAs($editor)->put(route('admin.articles.search-profile.update', $article), $this->validPayload());

        $response = $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $response->assertOk();
        $response->assertDontSee('Stessa query primaria anche in', false);
    }

    public function test_the_search_profile_is_never_rendered_on_the_public_article_page(): void
    {
        $article = $this->article();

        $this->actingAs($this->editor())->put(route('admin.articles.search-profile.update', $article), $this->validPayload([
            'primary_query' => 'query segreta unica xyz',
            'freshness_note' => 'nota interna riservatissima',
        ]));

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('query segreta unica xyz');
        $response->assertDontSee('nota interna riservatissima');
    }

    public function test_viewing_the_edit_page_performs_no_mutation_on_an_existing_profile(): void
    {
        $article = $this->article();
        $editor = $this->editor();

        $this->actingAs($editor)->put(route('admin.articles.search-profile.update', $article), $this->validPayload());
        $before = ArticleSearchProfile::query()->firstOrFail()->getAttributes();

        $this->actingAs($editor)->get(route('admin.articles.edit', $article));

        $this->assertSame($before, ArticleSearchProfile::query()->firstOrFail()->getAttributes());
    }
}
