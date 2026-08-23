<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ArticlePathNavigation;
use App\Services\ContentClusters\ContentClusterPublicSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentClusterContinuousPublishedPrefixTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = User::factory()->create(['role' => 'editor']);
    }

    public function test_published_published_published_scheduled_published_published_exposes_only_first_three(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
        ]);

        $resolved = app(ContentClusterPublicSequence::class)->resolve($cluster);

        $this->assertSame(
            array_column(array_slice($articles, 0, 3), 'id'),
            $resolved['articles']->pluck('id')->all(),
        );
        $this->assertTrue($resolved['has_hidden_remainder']);
    }

    public function test_published_scheduled_published_exposes_only_first_article(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
        ]);

        $resolved = app(ContentClusterPublicSequence::class)->resolve($cluster);

        $this->assertSame([$articles[0]['id']], $resolved['articles']->pluck('id')->all());
    }

    public function test_scheduled_first_step_exposes_zero_articles_even_when_later_steps_are_published(): void
    {
        [$cluster] = $this->path([
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
        ]);

        $resolved = app(ContentClusterPublicSequence::class)->resolve($cluster);

        $this->assertTrue($resolved['articles']->isEmpty());
        $this->assertTrue($resolved['has_hidden_remainder']);
    }

    public function test_all_published_steps_are_exposed(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
        ]);

        $resolved = app(ContentClusterPublicSequence::class)->resolve($cluster);

        $this->assertSame(array_column($articles, 'id'), $resolved['articles']->pluck('id')->all());
        $this->assertFalse($resolved['has_hidden_remainder']);
    }

    public function test_draft_gap_stops_the_prefix_and_hides_later_published_step(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_DRAFT,
            Article::STATUS_PUBLISHED,
        ]);

        $resolved = app(ContentClusterPublicSequence::class)->resolve($cluster);

        $this->assertSame(
            [$articles[0]['id'], $articles[1]['id']],
            $resolved['articles']->pluck('id')->all(),
        );
    }

    public function test_public_page_json_ld_and_copy_hide_everything_after_the_first_gap(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
        ], transitions: [
            10 => 'Verso due',
            20 => 'Verso tre',
            30 => 'Verso la tappa segreta',
        ]);

        $response = $this->get(route('percorsi.show', $cluster->slug));
        $response->assertOk();

        foreach (array_slice($articles, 0, 3) as $article) {
            $response->assertSee($article['title']);
            $response->assertSee(route('articolo', $article['slug']), false);
        }

        foreach (array_slice($articles, 3) as $article) {
            $response->assertDontSee($article['title']);
            $response->assertDontSee($article['slug']);
        }

        $response
            ->assertSee('In arrivo')
            ->assertSee('Verso due')
            ->assertSee('Verso tre')
            ->assertDontSee('Verso la tappa segreta');

        $html = $response->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(3, $schema['mainEntity']['numberOfItems']);
        $this->assertSame(
            array_column(array_slice($articles, 0, 3), 'title'),
            array_column($schema['mainEntity']['itemListElement'], 'name'),
        );
    }

    public function test_pillar_beyond_gap_is_not_rendered_or_linked(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
        ]);
        $cluster->update(['pillar_article_id' => $articles[2]['id']]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response
            ->assertOk()
            ->assertDontSee('Il punto di partenza')
            ->assertDontSee($articles[2]['title'])
            ->assertDontSee($articles[2]['slug']);
    }

    public function test_navigation_never_jumps_over_a_gap_to_a_later_published_member(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
        ]);

        $second = Article::findOrFail($articles[1]['id']);
        $later = Article::findOrFail($articles[3]['id']);

        $secondNavigation = app(ArticlePathNavigation::class)->forArticle($second);
        $laterNavigation = app(ArticlePathNavigation::class)->forArticle($later);

        $this->assertNotNull($secondNavigation);
        $this->assertNull($secondNavigation['next']);
        $this->assertNull($laterNavigation);
    }

    public function test_no_scheduled_metadata_or_hidden_count_leaks_through_in_arrivo(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
        ]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response
            ->assertOk()
            ->assertSee('In arrivo')
            ->assertDontSee($articles[1]['title'])
            ->assertDontSee($articles[1]['slug'])
            ->assertDontSee($articles[1]['published_at'])
            ->assertDontSee($articles[2]['title'])
            ->assertDontSee($articles[3]['title']);
    }

    public function test_prefix_extends_automatically_when_the_blocking_step_becomes_published(): void
    {
        [$cluster, $articles] = $this->path([
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_SCHEDULED,
            Article::STATUS_PUBLISHED,
            Article::STATUS_PUBLISHED,
        ]);

        $before = app(ContentClusterPublicSequence::class)->resolve($cluster);
        $this->assertSame(2, $before['articles']->count());

        Article::findOrFail($articles[2]['id'])->update([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $after = app(ContentClusterPublicSequence::class)->resolve($cluster->fresh());
        $this->assertSame(array_column($articles, 'id'), $after['articles']->pluck('id')->all());
        $this->assertFalse($after['has_hidden_remainder']);
    }

    /**
     * @param list<string> $statuses
     * @param array<int, string> $transitions keyed by position
     * @return array{0: ContentCluster, 1: list<array{id:int,title:string,slug:string,published_at:string}>}
     */
    private function path(array $statuses, array $transitions = []): array
    {
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso prefisso '.uniqid(),
            'slug' => 'percorso-prefisso-'.uniqid(),
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE,
        ]);

        $articles = [];

        foreach ($statuses as $index => $status) {
            $position = ($index + 1) * 10;
            $publishedAt = match ($status) {
                Article::STATUS_PUBLISHED => now()->subMinutes(10 - min($index, 9)),
                Article::STATUS_SCHEDULED => now()->addDays($index + 1),
                default => null,
            };
            $title = 'Tappa '.($index + 1).' '.uniqid();
            $article = Article::create([
                'user_id' => $this->editor->id,
                'title' => $title,
                'slug' => str($title)->slug(),
                'body' => 'Corpo della tappa.',
                'category' => 'energia',
                'status' => $status,
                'published_at' => $publishedAt,
            ]);

            $cluster->articles()->attach($article->id, [
                'position' => $position,
                'transition_text' => $transitions[$position] ?? null,
            ]);

            $articles[] = [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'published_at' => $article->published_at?->toISOString() ?? '',
            ];
        }

        return [$cluster, $articles];
    }
}
