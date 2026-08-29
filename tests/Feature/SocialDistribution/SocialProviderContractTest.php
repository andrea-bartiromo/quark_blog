<?php

namespace Tests\Feature\SocialDistribution;

use App\Models\Article;
use App\Models\SocialPublication;
use App\Models\User;
use App\Services\SocialDistribution\FakeSocialProvider;
use App\Services\SocialDistribution\SocialArticlePayloadFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialProviderContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_contains_editorial_data_but_no_transport_credentials(): void
    {
        $article = Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create(['role' => 'author'])->id,
            'title' => 'Titolo provider',
            'slug' => 'titolo-provider',
            'excerpt' => 'Descrizione provider',
            'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]));
        $publication = SocialPublication::create([
            'article_id' => $article->id,
            'channel' => 'facebook',
            'event_key' => 'article:'.$article->id.':published:test',
        ]);

        $payload = app(SocialArticlePayloadFactory::class)->forPublication($publication);
        $serialized = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->assertSame($article->id, $payload->articleId);
        $this->assertSame($article->metaCanonicalUrl(), $payload->canonicalUrl);
        $this->assertStringNotContainsString('token', strtolower($serialized));
        $this->assertStringNotContainsString('secret', strtolower($serialized));

        $provider = new FakeSocialProvider();
        $first = $provider->publishArticleDistribution($payload, $publication->event_key);
        $second = $provider->publishArticleDistribution($payload, $publication->event_key);
        $this->assertSame($first->remoteId, $second->remoteId);
    }
}
