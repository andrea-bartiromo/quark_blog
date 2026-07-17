<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicCommentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_is_accepted_for_published_article(): void
    {
        Mail::fake();

        $article = $this->createArticle('published');

        $response = $this->postJson(route('commenti.store'), $this->validPayload($article->id));

        $response
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Commento inviato. Sarà pubblicato dopo la moderazione.',
            ]);

        $this->assertDatabaseHas('comments', [
            'article_id' => $article->id,
            'email' => 'lettrice@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_comment_is_rejected_for_draft_article(): void
    {
        Mail::fake();

        $article = $this->createArticle('draft');

        $response = $this->postJson(route('commenti.store'), $this->validPayload($article->id));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('article_id');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_is_rejected_for_article_in_review(): void
    {
        Mail::fake();

        $article = $this->createArticle('review');

        $response = $this->postJson(route('commenti.store'), $this->validPayload($article->id));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('article_id');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_comment_is_rejected_for_missing_article(): void
    {
        Mail::fake();

        $response = $this->postJson(route('commenti.store'), $this->validPayload(999999));

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('article_id');

        $this->assertDatabaseCount('comments', 0);
    }

    private function createArticle(string $status): Article
    {
        $author = User::create([
            'name' => 'Autrice Test',
            'email' => uniqid('author_', true) . '@example.com',
            'password' => 'password',
            'role' => 'editor',
        ]);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo ' . $status,
            'slug' => 'articolo-' . $status . '-' . uniqid(),
            'excerpt' => 'Estratto di test per il commento pubblico.',
            'body' => 'Corpo articolo di test.',
            'category' => 'spazio',
            'status' => $status,
            'featured' => false,
            'read_minutes' => 1,
            'views' => 0,
            'published_at' => $status === 'published' ? now() : null,
            'verification_status' => 'unverified',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(int $articleId): array
    {
        return [
            'article_id' => $articleId,
            'name' => 'Lettrice Test',
            'email' => 'lettrice@example.com',
            'body' => 'Questo è un commento pubblico valido per il test.',
            'website' => '',
        ];
    }
}
