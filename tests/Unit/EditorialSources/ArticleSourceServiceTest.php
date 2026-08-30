<?php

namespace Tests\Unit\EditorialSources;

use App\Models\Article;
use App\Models\ArticleSource;
use App\Models\User;
use App\Services\EditorialSources\ArticleSourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EDITORIAL TRUST (Missione 27) — riconciliazione dell'elenco fonti.
 */
class ArticleSourceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function article(): Article
    {
        $author = User::factory()->create(['role' => 'editor']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ]);
    }

    public function test_sync_creates_sources_with_sequential_positions(): void
    {
        $article = $this->article();
        $service = app(ArticleSourceService::class);

        $count = $service->sync($article, [
            ['title' => 'Prima fonte', 'url' => 'https://www.esa.int/uno'],
            ['title' => 'Seconda fonte', 'url' => 'https://www.esa.int/due'],
        ]);

        $this->assertSame(2, $count);
        $this->assertSame(
            ['Prima fonte', 'Seconda fonte'],
            $article->sources()->pluck('title')->all()
        );
        $this->assertSame([0, 1], $article->sources()->pluck('position')->all());
    }

    public function test_sync_updates_an_existing_source_by_id_instead_of_duplicating_it(): void
    {
        $article = $this->article();
        $service = app(ArticleSourceService::class);

        $service->sync($article, [
            ['title' => 'Titolo originale', 'url' => 'https://www.esa.int/uno'],
        ]);

        $existingId = $article->sources()->first()->id;

        $service->sync($article, [
            ['id' => $existingId, 'title' => 'Titolo corretto', 'url' => 'https://www.esa.int/uno'],
        ]);

        $this->assertDatabaseCount('article_sources', 1);
        $this->assertSame('Titolo corretto', $article->sources()->first()->title);
    }

    public function test_sync_removes_a_source_absent_from_the_new_payload(): void
    {
        $article = $this->article();
        $service = app(ArticleSourceService::class);

        $service->sync($article, [
            ['title' => 'Da tenere', 'url' => 'https://www.esa.int/uno'],
            ['title' => 'Da rimuovere', 'url' => 'https://www.esa.int/due'],
        ]);

        $keepId = $article->sources()->where('title', 'Da tenere')->first()->id;

        $count = $service->sync($article, [
            ['id' => $keepId, 'title' => 'Da tenere', 'url' => 'https://www.esa.int/uno'],
        ]);

        $this->assertSame(1, $count);
        $this->assertDatabaseCount('article_sources', 1);
        $this->assertDatabaseHas('article_sources', ['title' => 'Da tenere']);
    }

    public function test_sync_ignores_an_id_belonging_to_another_article_and_creates_a_new_row_instead(): void
    {
        $articleA = $this->article();
        $articleB = $this->article();
        $service = app(ArticleSourceService::class);

        $service->sync($articleA, [
            ['title' => 'Fonte di A', 'url' => 'https://www.esa.int/a'],
        ]);
        $foreignId = $articleA->sources()->first()->id;

        // Un id che appartiene ad articleA non deve permettere di
        // aggiornare la sua riga da qui: deve essere trattato come nuovo.
        $service->sync($articleB, [
            ['id' => $foreignId, 'title' => 'Fonte di B', 'url' => 'https://www.esa.int/b'],
        ]);

        $this->assertDatabaseHas('article_sources', ['title' => 'Fonte di A', 'article_id' => $articleA->id]);
        $this->assertDatabaseHas('article_sources', ['title' => 'Fonte di B', 'article_id' => $articleB->id]);
        $this->assertDatabaseCount('article_sources', 2);
    }

    public function test_sync_normalizes_url_and_doi_at_write_time(): void
    {
        $article = $this->article();
        $service = app(ArticleSourceService::class);

        $service->sync($article, [
            ['title' => 'Studio', 'doi' => 'https://doi.org/10.1038/x-1'],
        ]);

        $this->assertSame('10.1038/x-1', $article->sources()->first()->doi);
    }

    public function test_sync_rejects_unsafe_url_at_write_time_even_if_validation_was_bypassed(): void
    {
        $article = $this->article();
        $service = app(ArticleSourceService::class);

        $service->sync($article, [
            ['title' => 'Fonte sospetta', 'url' => 'javascript:alert(1)'],
        ]);

        $this->assertNull($article->sources()->first()->url);
    }

    public function test_sync_defaults_an_unrecognised_source_type_to_unknown(): void
    {
        $article = $this->article();
        $service = app(ArticleSourceService::class);

        $service->sync($article, [
            ['title' => 'Fonte', 'url' => 'https://www.esa.int/uno', 'source_type' => 'peer_reviewed'],
        ]);

        $this->assertSame(ArticleSource::TYPE_UNKNOWN, $article->sources()->first()->source_type);
    }

    public function test_duplicate_warnings_flags_two_rows_sharing_the_same_doi(): void
    {
        $service = app(ArticleSourceService::class);

        $warnings = $service->duplicateWarnings([
            ['title' => 'Prima citazione', 'doi' => '10.1038/x-1'],
            ['title' => 'Seconda citazione', 'doi' => 'https://doi.org/10.1038/x-1'],
        ]);

        $this->assertSame(['Seconda citazione'], $warnings);
    }

    public function test_duplicate_warnings_is_empty_when_every_reference_is_distinct(): void
    {
        $service = app(ArticleSourceService::class);

        $warnings = $service->duplicateWarnings([
            ['title' => 'Uno', 'url' => 'https://www.esa.int/uno'],
            ['title' => 'Due', 'url' => 'https://www.esa.int/due'],
        ]);

        $this->assertSame([], $warnings);
    }
}
