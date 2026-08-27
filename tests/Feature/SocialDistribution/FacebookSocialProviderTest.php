<?php

namespace Tests\Feature\SocialDistribution;

use App\Models\Article;
use App\Models\User;
use App\Services\SocialDistribution\FacebookSocialProvider;
use App\Services\SocialDistribution\SocialArticlePayload;
use App\Services\SocialDistribution\SocialProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookSocialProviderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): SocialArticlePayload
    {
        $article = Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create(['role' => 'author'])->id,
            'title' => 'Titolo Facebook',
            'slug' => 'titolo-facebook-'.uniqid(),
            'excerpt' => 'Copy breve Facebook',
            'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia',
            'cover_image' => 'cover.webp',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]));

        return new SocialArticlePayload($article->id, $article->title, $article->excerpt, $article->metaCanonicalUrl(), 'https://kairus.it/assets/img/cover.webp');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'social_distribution.facebook.page_id' => 'page-123',
            'social_distribution.facebook.access_token' => 'test-secret-token',
            'social_distribution.facebook.graph_url' => 'https://graph.facebook.test',
            'social_distribution.facebook.graph_version' => 'v23.0',
        ]);
    }

    public function test_builds_link_post_with_utm_and_normalizes_success(): void
    {
        Http::fake(['graph.facebook.test/*' => Http::response(['id' => 'page-123_456', 'permalink_url' => 'https://facebook.test/post/456'])]);

        $result = app(FacebookSocialProvider::class)->publishArticleDistribution($this->payload(), 'event-key');

        $this->assertSame('page-123_456', $result->remoteId);
        Http::assertSent(function (Request $request) {
            $data = $request->data();
            return str_ends_with($request->url(), '/v23.0/page-123/feed')
                && str_contains($data['message'], 'Titolo Facebook')
                && str_contains($data['link'], 'utm_source=facebook')
                && $data['picture'] === 'https://kairus.it/assets/img/cover.webp'
                && $data['access_token'] === 'test-secret-token';
        });
    }

    public function test_maps_expired_token_and_rate_limit_without_exposing_raw_response(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['code' => 190, 'message' => 'raw secret']], 400)
            ->push(['error' => ['code' => 4, 'message' => 'raw secret']], 429);

        foreach ([
            ['facebook_token_expired', false],
            ['facebook_rate_limited', true],
        ] as [$expected, $retryable]) {
            try {
                app(FacebookSocialProvider::class)->publishArticleDistribution($this->payload(), 'event-key');
                $this->fail('Eccezione provider attesa.');
            } catch (SocialProviderException $exception) {
                $this->assertSame($expected, $exception->getMessage());
                $this->assertSame($retryable, $exception->retryable);
                $this->assertStringNotContainsString('raw secret', $exception->getMessage());
            }
        }
    }

    public function test_missing_configuration_fails_before_any_http_call(): void
    {
        config(['social_distribution.facebook.access_token' => null]);
        Http::fake();

        $this->expectException(SocialProviderException::class);
        try {
            app(FacebookSocialProvider::class)->publishArticleDistribution($this->payload(), 'event-key');
        } finally {
            Http::assertNothingSent();
        }
    }
}
