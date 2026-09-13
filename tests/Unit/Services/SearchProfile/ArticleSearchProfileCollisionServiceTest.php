<?php

namespace Tests\Unit\Services\SearchProfile;

use App\Models\Article;
use App\Models\ArticleSearchProfile;
use App\Models\User;
use App\Services\SearchProfile\ArticleSearchProfileCollisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSearchProfileCollisionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ArticleSearchProfileCollisionService
    {
        return app(ArticleSearchProfileCollisionService::class);
    }

    private function articleWithProfile(string $primaryQuery, array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        $article = Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'natura',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ], $overrides));

        ArticleSearchProfile::create([
            'article_id' => $article->id,
            'primary_query' => $primaryQuery,
        ]);

        return $article;
    }

    public function test_returns_empty_when_no_other_article_shares_the_query(): void
    {
        $article = $this->articleWithProfile('ape insetto');

        $this->assertTrue($this->service()->collidingArticles($article, 'ape insetto')->isEmpty());
    }

    public function test_finds_a_collision_after_case_and_whitespace_normalization(): void
    {
        $other = $this->articleWithProfile('Ape Insetto');
        $article = $this->articleWithProfile('  ape   insetto  ');

        $collisions = $this->service()->collidingArticles($article, 'ape insetto');

        $this->assertCount(1, $collisions);
        $this->assertSame($other->id, $collisions->first()->id);
    }

    public function test_finds_a_collision_after_trailing_punctuation_and_curly_quote_normalization(): void
    {
        $other = $this->articleWithProfile("quanto vive un'ape?");
        $article = $this->articleWithProfile("quanto vive un\u{2019}ape");

        $collisions = $this->service()->collidingArticles($article, "quanto vive un\u{2019}ape");

        $this->assertCount(1, $collisions);
        $this->assertSame($other->id, $collisions->first()->id);
    }

    public function test_returns_empty_for_a_null_or_blank_primary_query(): void
    {
        $article = $this->articleWithProfile('ape insetto');

        $this->assertTrue($this->service()->collidingArticles($article, null)->isEmpty());
        $this->assertTrue($this->service()->collidingArticles($article, '   ')->isEmpty());
    }

    public function test_does_not_collide_with_itself(): void
    {
        $article = $this->articleWithProfile('ape insetto');

        $this->assertTrue($this->service()->collidingArticles($article, 'ape insetto')->isEmpty());
    }

    public function test_ignores_articles_without_a_primary_query(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $withoutQuery = Article::create([
            'user_id' => $author->id,
            'title' => 'Senza query',
            'slug' => 'senza-query-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'natura',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
        ArticleSearchProfile::create(['article_id' => $withoutQuery->id, 'primary_query' => null]);

        $article = $this->articleWithProfile('ape insetto');

        $this->assertTrue($this->service()->collidingArticles($article, 'ape insetto')->isEmpty());
    }
}
