<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Newsletter;
use App\Models\NewsletterClick;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NewsletterTrackingResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_root_is_an_expected_404(): void
    {
        $this->get('/newsletter')->assertNotFound();
    }

    public function test_valid_click_is_recorded_and_redirects_to_article(): void
    {
        $subscriber = Newsletter::subscribe('reader@example.com');
        $article = Article::factory()->create([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('newsletter.click', [$subscriber->id, $article->id]));

        $response->assertRedirect(route('articolo', $article->slug));
        $this->assertDatabaseHas('newsletter_clicks', [
            'newsletter_subscriber_id' => $subscriber->id,
            'article_id' => $article->id,
        ]);
    }

    public function test_missing_click_models_return_404_instead_of_500(): void
    {
        $this->get('/newsletter/click/999999/999999')->assertNotFound();
    }

    public function test_click_tracking_failure_does_not_block_article_redirect(): void
    {
        Log::spy();

        $subscriber = Newsletter::subscribe('fail-open@example.com');
        $article = Article::factory()->create([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        NewsletterClick::creating(function () {
            throw new \RuntimeException('simulated tracking failure');
        });

        $response = $this->get(route('newsletter.click', [$subscriber->id, $article->id]));

        $response->assertRedirect(route('articolo', $article->slug));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_open_tracking_returns_pixel_for_valid_subscriber(): void
    {
        $subscriber = Newsletter::subscribe('pixel@example.com');

        $this->get(route('newsletter.open', $subscriber->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');
    }
}
