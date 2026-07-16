<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_still_redirected_to_login(): void
    {
        $this->get(route('admin.stats'))
            ->assertRedirect(route('login'));
    }

    public function test_author_is_still_redirected_to_the_editorial_dashboard(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.stats'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_view_expected_statistics_when_approved_comments_exist(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $article = Article::create([
            'user_id' => $editor->id,
            'title' => 'Articolo con statistiche',
            'slug' => 'articolo-con-statistiche',
            'excerpt' => 'Sommario',
            'body' => 'Corpo articolo.',
            'category' => 'energia',
            'status' => 'published',
            'views' => 1234,
            'read_minutes' => 5,
            'published_at' => now(),
        ]);

        Comment::create([
            'article_id' => $article->id,
            'name' => 'Lettore Uno',
            'email' => 'lettore1@example.com',
            'body' => 'Primo commento.',
            'status' => 'approved',
        ]);
        Comment::create([
            'article_id' => $article->id,
            'name' => 'Lettore Due',
            'email' => 'lettore2@example.com',
            'body' => 'Secondo commento.',
            'status' => 'approved',
        ]);
        Comment::create([
            'article_id' => $article->id,
            'name' => 'Lettore Tre',
            'email' => 'lettore3@example.com',
            'body' => 'Commento in moderazione.',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($editor)->get(route('admin.stats'));

        $response->assertOk();
        $response->assertSeeTextInOrder([
            'Views totali',
            '1.234',
            'Articoli',
            '1',
            'Commenti',
            '2',
            'Top article',
            '1.234',
        ]);
        $response->assertSeeText('Articolo con statistiche');
        $response->assertSee(route('admin.articles.edit', $article), false);
    }
}
