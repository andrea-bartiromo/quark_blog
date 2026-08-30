<?php

namespace Tests\Feature\Admin\EditorialSources;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL TRUST (Missione 27) — superficie admin dell'editor fonti.
 */
class ArticleSourceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $author): Article
    {
        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo esistente',
            'slug' => 'articolo-esistente-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $article = $this->article($this->editor());

        $response = $this->put(route('admin.articles.sources.update', $article), ['sources' => []]);

        $response->assertRedirect(route('login'));
    }

    public function test_editor_can_save_a_valid_source_with_an_https_url(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                ['title' => 'Comunicato ESA', 'url' => 'https://www.esa.int/comunicato'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('article_sources', [
            'article_id' => $article->id,
            'title' => 'Comunicato ESA',
            'url' => 'https://www.esa.int/comunicato',
        ]);
    }

    public function test_a_source_without_url_or_doi_is_rejected_and_nothing_is_saved(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                ['title' => 'Fonte senza riferimento'],
            ],
        ]);

        $response->assertSessionHasErrors('sources.0.url');
        $this->assertDatabaseCount('article_sources', 0);
    }

    public function test_a_javascript_url_is_rejected(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                ['title' => 'Fonte sospetta', 'url' => 'javascript:alert(1)'],
            ],
        ]);

        $response->assertSessionHasErrors('sources.0.url');
        $this->assertDatabaseCount('article_sources', 0);
    }

    public function test_an_invalid_source_does_not_touch_previously_saved_sources(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                ['title' => 'Fonte valida', 'url' => 'https://www.esa.int/uno'],
            ],
        ]);

        $this->assertDatabaseCount('article_sources', 1);

        // Un secondo invio non valido non deve alterare la fonte già
        // salvata: la validazione fallisce PRIMA di ArticleSourceService.
        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                ['title' => 'Fonte valida', 'url' => 'https://www.esa.int/uno'],
                ['title' => 'Fonte senza riferimento'],
            ],
        ]);

        $response->assertSessionHasErrors('sources.1.url');
        $this->assertDatabaseCount('article_sources', 1);
        $this->assertDatabaseHas('article_sources', ['title' => 'Fonte valida']);
    }

    public function test_entirely_blank_rows_are_silently_dropped(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                ['title' => '', 'url' => '', 'doi' => '', 'author_or_org' => '', 'published_on' => '', 'accessed_on' => '', 'editorial_note' => ''],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('article_sources', 0);
    }

    public function test_a_future_accessed_date_is_rejected(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                [
                    'title' => 'Fonte',
                    'url' => 'https://www.esa.int/uno',
                    'accessed_on' => now()->addDay()->toDateString(),
                ],
            ],
        ]);

        $response->assertSessionHasErrors('sources.0.accessed_on');
        $this->assertDatabaseCount('article_sources', 0);
    }

    public function test_duplicate_references_are_saved_but_flagged_as_a_warning(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => [
                ['title' => 'Prima citazione', 'doi' => '10.1038/x-1'],
                ['title' => 'Seconda citazione', 'doi' => '10.1038/x-1'],
            ],
        ]);

        $response->assertSessionHas('warning');
        $this->assertDatabaseCount('article_sources', 2);
    }

    public function test_more_than_the_maximum_number_of_sources_is_rejected(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor);

        $rows = [];
        for ($i = 0; $i < 51; $i++) {
            $rows[] = ['title' => 'Fonte '.$i, 'url' => 'https://www.esa.int/'.$i];
        }

        $response = $this->actingAs($editor)->put(route('admin.articles.sources.update', $article), [
            'sources' => $rows,
        ]);

        $response->assertSessionHasErrors('sources');
        $this->assertDatabaseCount('article_sources', 0);
    }
}
