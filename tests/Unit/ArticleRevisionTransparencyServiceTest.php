<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\User;
use App\Services\ArticleRevisionTransparencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleRevisionTransparencyServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ArticleRevisionTransparencyService
    {
        return new ArticleRevisionTransparencyService;
    }

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Titolo attuale',
            'slug' => 'articolo-'.uniqid(),
            'excerpt' => 'Sommario attuale',
            'body' => '<p>Corpo attuale.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now()->subDays(10),
        ], $overrides));
    }

    public function test_never_published_article_returns_null(): void
    {
        $article = Article::create([
            'user_id' => $this->author()->id,
            'title' => 'Bozza',
            'slug' => 'bozza-'.uniqid(),
            'body' => '<p>Bozza.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'draft',
        ]);

        $this->assertNull($this->service()->lastEditorialUpdate($article));
    }

    public function test_article_with_no_revisions_at_all_returns_null(): void
    {
        $article = $this->publishedArticle();

        $this->assertNull($this->service()->lastEditorialUpdate($article));
    }

    public function test_revision_that_predates_publication_is_ignored(): void
    {
        $article = $this->publishedArticle();

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => 'Titolo pre-pubblicazione diverso',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'draft',
            'created_at' => $article->published_at->clone()->subDay(),
        ]);

        $this->assertNull($this->service()->lastEditorialUpdate($article));
    }

    public function test_status_only_revision_after_publication_does_not_count_as_an_update(): void
    {
        $article = $this->publishedArticle();

        // Stesso contenuto della versione attuale: solo lo status/published_at
        // differivano al momento dello snapshot (es. un repubblicazione).
        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'review',
            'created_at' => $article->published_at->clone()->addHour(),
        ]);

        $this->assertNull($this->service()->lastEditorialUpdate($article));
    }

    public function test_genuine_content_change_after_publication_is_detected(): void
    {
        $article = $this->publishedArticle();

        $revisionTime = $article->published_at->clone()->addDays(3);

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => 'Titolo prima della correzione',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'published',
            'created_at' => $revisionTime,
        ]);

        $result = $this->service()->lastEditorialUpdate($article);

        $this->assertNotNull($result);
        $this->assertSame($revisionTime->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    public function test_most_recent_qualifying_revision_timestamp_is_used(): void
    {
        $article = $this->publishedArticle();

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => 'Prima correzione',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'published',
            'created_at' => $article->published_at->clone()->addDay(),
        ]);

        $secondRevisionTime = $article->published_at->clone()->addDays(5);

        ArticleRevision::create([
            'article_id' => $article->id,
            'title' => 'Seconda correzione',
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'category' => $article->category,
            'status' => 'published',
            'created_at' => $secondRevisionTime,
        ]);

        $result = $this->service()->lastEditorialUpdate($article);

        $this->assertSame($secondRevisionTime->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }
}
