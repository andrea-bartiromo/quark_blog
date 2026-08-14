<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_root_has_no_public_page(): void
    {
        $this->get('/newsletter')->assertNotFound();
    }

    public function test_valid_click_tracks_with_migration_column_and_redirects_to_article(): void
    {
        $subscriber = $this->subscriber();
        $article = $this->article();

        $response = $this->get(route('newsletter.click', [$subscriber->id, $article->id]));

        $response->assertRedirect(route('articolo', $article->slug));
        $this->assertDatabaseHas('newsletter_clicks', [
            'newsletter_subscriber_id' => $subscriber->id,
            'article_id' => $article->id,
            'email' => $subscriber->email,
        ]);
    }

    public function test_missing_subscriber_still_redirects_to_existing_article_without_tracking(): void
    {
        $article = $this->article();

        $response = $this->get(route('newsletter.click', [999999, $article->id]));

        $response->assertRedirect(route('articolo', $article->slug));
        $this->assertDatabaseCount('newsletter_clicks', 0);
    }

    public function test_deleted_article_link_falls_back_to_news_index(): void
    {
        $subscriber = $this->subscriber();
        $article = $this->article();
        $trackingUrl = route('newsletter.click', [$subscriber->id, $article->id]);

        $article->delete();

        $this->get($trackingUrl)->assertRedirect(route('notizie'));
        $this->assertDatabaseCount('newsletter_clicks', 0);
    }

    public function test_missing_subscriber_open_pixel_remains_available(): void
    {
        $response = $this->get(route('newsletter.open', [999999]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');
        $this->assertDatabaseCount('newsletter_opens', 0);
    }

    private function subscriber(): Newsletter
    {
        return Newsletter::create([
            'email' => 'tracking@example.com',
            'confirmed' => true,
            'token' => null,
            'unsubscribe_token' => 'tracking-unsubscribe-token',
        ]);
    }

    private function article(): Article
    {
        $user = User::factory()->create();

        return Article::create([
            'user_id' => $user->id,
            'title' => 'Newsletter tracking article',
            'slug' => 'newsletter-tracking-article',
            'body' => 'Corpo.',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subMinute(),
        ]);
    }
}
