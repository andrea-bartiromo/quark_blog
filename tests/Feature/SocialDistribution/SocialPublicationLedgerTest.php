<?php

namespace Tests\Feature\SocialDistribution;

use App\Models\Article;
use App\Models\SocialPublication;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SocialPublicationLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function article(): Article
    {
        return Article::create([
            'user_id' => User::factory()->create(['role' => 'author'])->id,
            'title' => 'Articolo social',
            'slug' => 'articolo-social-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'tecnologia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function test_schema_is_secret_free_and_portable(): void
    {
        $columns = Schema::getColumnListing('social_publications');

        $this->assertContains('article_id', $columns);
        $this->assertContains('event_key', $columns);
        $this->assertContains('attempt_count', $columns);
        $this->assertNotContains('token', $columns);
        $this->assertNotContains('secret', $columns);
        $this->assertNotContains('response_body', $columns);
    }

    public function test_logical_delivery_is_unique_per_article_channel_and_event(): void
    {
        $article = $this->article();
        $attributes = [
            'article_id' => $article->id,
            'channel' => 'facebook',
            'event_key' => 'article:'.$article->id.':published:20260827T150000Z',
        ];

        SocialPublication::create($attributes);

        $this->expectException(QueryException::class);
        SocialPublication::create($attributes);
    }

    public function test_same_event_can_have_one_delivery_per_channel(): void
    {
        $article = $this->article();
        $eventKey = 'article:'.$article->id.':published:20260827T150000Z';

        foreach (['facebook', 'instagram'] as $channel) {
            SocialPublication::create([
                'article_id' => $article->id,
                'channel' => $channel,
                'event_key' => $eventKey,
            ]);
        }

        $this->assertSame(2, SocialPublication::count());
    }

    public function test_deleting_an_article_removes_its_ledger_rows(): void
    {
        $article = $this->article();
        SocialPublication::create([
            'article_id' => $article->id,
            'channel' => 'facebook',
            'event_key' => 'article:'.$article->id.':published:test',
        ]);

        $article->delete();

        $this->assertDatabaseCount('social_publications', 0);
    }
}
