<?php

namespace Tests\Unit\SearchConsole;

use App\Models\Article;
use App\Models\User;
use App\Services\SearchConsole\SearchConsoleQueryArticleMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchConsoleQueryArticleMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_a_full_article_url_to_the_real_article(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Onde gravitazionali',
            'slug' => 'onde-gravitazionali',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $match = (new SearchConsoleQueryArticleMatcher)->match('https://kairus.it/articolo/onde-gravitazionali');

        $this->assertNotNull($match);
        $this->assertSame($article->id, $match->id);
    }

    public function test_matches_with_a_trailing_slash(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Onde gravitazionali',
            'slug' => 'onde-gravitazionali',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $match = (new SearchConsoleQueryArticleMatcher)->match('https://kairus.it/articolo/onde-gravitazionali/');

        $this->assertNotNull($match);
    }

    public function test_returns_null_for_a_slug_that_does_not_exist(): void
    {
        $match = (new SearchConsoleQueryArticleMatcher)->match('https://kairus.it/articolo/non-esiste');

        $this->assertNull($match);
    }

    public function test_returns_null_for_a_non_article_page(): void
    {
        $this->assertNull((new SearchConsoleQueryArticleMatcher)->match('https://kairus.it/'));
        $this->assertNull((new SearchConsoleQueryArticleMatcher)->match('https://kairus.it/categoria/spazio'));
        $this->assertNull((new SearchConsoleQueryArticleMatcher)->match('https://kairus.it/percorsi/spazio'));
    }

    public function test_returns_null_for_a_malformed_url(): void
    {
        $this->assertNull((new SearchConsoleQueryArticleMatcher)->match('not a url at all ://'));
    }
}
