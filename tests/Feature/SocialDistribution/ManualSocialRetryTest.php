<?php

namespace Tests\Feature\SocialDistribution;

use App\Jobs\PublishSocialDistribution;
use App\Models\Article;
use App\Models\SocialPublication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ManualSocialRetryTest extends TestCase
{
    use RefreshDatabase;

    private function article(User $author): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => $author->id,
            'title' => 'Retry social '.uniqid(),
            'slug' => 'retry-social-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia',
            'status' => Article::STATUS_DRAFT,
        ]));
    }

    public function test_retry_reuses_only_a_retryable_logical_delivery(): void
    {
        config(['social_distribution.enabled' => true]);
        Bus::fake();
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->article($editor);
        $publication = SocialPublication::create(['article_id' => $article->id, 'channel' => 'facebook', 'event_key' => 'article:'.$article->id, 'status' => SocialPublication::STATUS_RETRYABLE]);

        $this->actingAs($editor)->post(route('admin.articles.social-publications.retry', [$article, $publication]))->assertRedirect();

        $this->assertDatabaseCount('social_publications', 1);
        Bus::assertDispatched(PublishSocialDistribution::class, fn ($job) => $job->publicationId === $publication->id);
        $this->assertDatabaseHas('activity_log', ['action' => 'Retry consegna social richiesto', 'subject_id' => $publication->id]);
    }

    public function test_successful_delivery_is_never_republished(): void
    {
        config(['social_distribution.enabled' => true]);
        Bus::fake();
        $editor = User::factory()->create(['role' => 'editor']);
        $article = $this->article($editor);
        $publication = SocialPublication::create(['article_id' => $article->id, 'channel' => 'facebook', 'event_key' => 'article:'.$article->id, 'status' => SocialPublication::STATUS_SUCCEEDED]);

        $this->actingAs($editor)->post(route('admin.articles.social-publications.retry', [$article, $publication]))->assertRedirect();
        Bus::assertNotDispatched(PublishSocialDistribution::class);
        $this->assertDatabaseCount('social_publications', 1);
    }
}
