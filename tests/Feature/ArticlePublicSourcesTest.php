<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticlePublicSourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_sources_are_publicly_visible_and_safely_linked(): void
    {
        $article = $this->article([
            'primary_sources' => "NASA — https://www.nasa.gov/example\nStudio — 10.1038/s41586-026-00001-2",
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('Fonti e approfondimenti');
        $response->assertSee('href="https://www.nasa.gov/example"', false);
        $response->assertSee('href="https://doi.org/10.1038/s41586-026-00001-2"', false);
        $response->assertSee('rel="external noopener noreferrer"', false);
    }

    public function test_source_markup_is_escaped_and_unsafe_schemes_are_plain_text(): void
    {
        $article = $this->article([
            'primary_sources' => '<script>alert(1)</script>'."\n".'javascript:alert(2)',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertDontSee('href="javascript:', false);
    }

    public function test_legacy_body_sources_remain_visible_when_primary_sources_are_empty(): void
    {
        $article = $this->article([
            'body' => '<p>Corpo principale.</p>---Fonte legacy verificata',
            'primary_sources' => null,
        ]);

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertSee('Fonti e approfondimenti')
            ->assertSee('Fonte legacy verificata');
    }

    public function test_no_empty_sources_section_is_rendered(): void
    {
        $article = $this->article(['primary_sources' => null]);

        $this->get(route('articolo', $article->slug))
            ->assertOk()
            ->assertDontSee('Fonti e approfondimenti');
    }

    private function article(array $overrides = []): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo con fonti',
            'slug' => 'articolo-con-fonti-'.uniqid(),
            'excerpt' => 'Sommario sufficientemente descrittivo.',
            'body' => '<p>Corpo principale.</p>',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 4,
        ], $overrides));
    }
}
