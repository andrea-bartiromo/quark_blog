<?php

namespace Tests\Feature\SocialDistribution;

use App\Events\ArticlePublished;
use App\Jobs\PublishSocialDistribution;
use App\Listeners\QueueSocialPublications;
use App\Models\Article;
use App\Models\SocialPublication;
use App\Models\User;
use App\Services\SocialDistribution\FakeSocialProvider;
use App\Services\SocialDistribution\SocialProviderException;
use App\Services\SocialDistribution\SocialProviderRegistry;
use App\Services\SocialDistribution\SocialArticlePayloadFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PublishSocialDistributionJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['social_distribution.channels.facebook.provider' => FakeSocialProvider::class]);
    }

    private function article(): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create(['role' => 'author'])->id,
            'title' => 'Distribuzione asincrona',
            'slug' => 'distribuzione-asincrona-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]));
    }

    private function publication(string $status = SocialPublication::STATUS_PENDING): SocialPublication
    {
        $article = $this->article();

        return SocialPublication::create([
            'article_id' => $article->id,
            'channel' => 'facebook',
            'event_key' => 'article:'.$article->id.':published:test',
            'status' => $status,
        ]);
    }

    public function test_listener_creates_and_dispatches_each_logical_delivery_only_once(): void
    {
        Queue::fake();
        config([
            'social_distribution.enabled' => true,
            'social_distribution.channels.facebook.enabled' => true,
            'social_distribution.channels.instagram.enabled' => false,
        ]);
        $event = new ArticlePublished($this->article());
        $listener = app(QueueSocialPublications::class);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('social_publications', 1);
        Queue::assertPushed(PublishSocialDistribution::class, 1);
    }

    public function test_reexecuted_job_does_not_publish_a_succeeded_delivery_twice(): void
    {
        $publication = $this->publication();
        $job = new PublishSocialDistribution($publication->id);
        $registry = app(SocialProviderRegistry::class);

        $job->handle($registry, app(SocialArticlePayloadFactory::class));
        $job->handle($registry, app(SocialArticlePayloadFactory::class));

        $publication->refresh();
        $this->assertSame(SocialPublication::STATUS_SUCCEEDED, $publication->status);
        $this->assertSame(1, $publication->attempt_count);
        $this->assertNotNull($publication->remote_id);
    }

    public function test_retryable_failure_is_reclaimed_once_and_can_then_succeed(): void
    {
        $publication = $this->publication();
        $provider = new FakeSocialProvider();
        $provider->nextFailure = new SocialProviderException('rate_limited', true);
        $this->app->instance(FakeSocialProvider::class, $provider);
        $job = new PublishSocialDistribution($publication->id);

        try {
            $job->handle(app(SocialProviderRegistry::class), app(SocialArticlePayloadFactory::class));
            $this->fail('Il fallimento retryable deve essere rilanciato alla coda.');
        } catch (SocialProviderException) {
            // expected
        }

        $this->assertSame(SocialPublication::STATUS_RETRYABLE, $publication->fresh()->status);
        $job->handle(app(SocialProviderRegistry::class), app(SocialArticlePayloadFactory::class));

        $publication->refresh();
        $this->assertSame(SocialPublication::STATUS_SUCCEEDED, $publication->status);
        $this->assertSame(2, $publication->attempt_count);
    }

    public function test_non_retryable_failure_is_terminal_and_sanitized(): void
    {
        $publication = $this->publication();
        $provider = new FakeSocialProvider();
        $provider->nextFailure = new SocialProviderException('token_expired', false);
        $this->app->instance(FakeSocialProvider::class, $provider);

        (new PublishSocialDistribution($publication->id))->handle(
            app(SocialProviderRegistry::class),
            app(SocialArticlePayloadFactory::class),
        );

        $publication->refresh();
        $this->assertSame(SocialPublication::STATUS_FAILED, $publication->status);
        $this->assertSame('provider_error', $publication->last_error_class);
        $this->assertSame('token_expired', $publication->last_error_message);
    }

    public function test_unexpected_exception_never_persists_its_message_or_secret(): void
    {
        $publication = $this->publication();
        $provider = new FakeSocialProvider();
        $provider->nextFailure = new RuntimeException('access_token=super-secret-value');
        $this->app->instance(FakeSocialProvider::class, $provider);

        (new PublishSocialDistribution($publication->id))->handle(
            app(SocialProviderRegistry::class),
            app(SocialArticlePayloadFactory::class),
        );

        $publication->refresh();
        $this->assertSame(SocialPublication::STATUS_FAILED, $publication->status);
        $this->assertSame('unexpected_error', $publication->last_error_class);
        $this->assertSame('unexpected_exception', $publication->last_error_message);
        $this->assertStringNotContainsString('super-secret-value', (string) $publication->last_error_message);
    }
}
