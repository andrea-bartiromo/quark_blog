<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\Discovery\ArticleDiscoveryAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleDiscoveryAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_articles_are_audited_and_generic_archive_is_a_real_path(): void
    {
        $published = $this->article('discovery-published', Article::STATUS_PUBLISHED);
        $this->article('discovery-draft', Article::STATUS_DRAFT);
        $this->article('discovery-scheduled', Article::STATUS_SCHEDULED);

        $rows = app(ArticleDiscoveryAuditService::class)->audit();
        $row = $rows->first();

        $this->assertSame([$published->id], $rows->pluck('article_id')->all());
        $this->assertSame(1, $row['archive_page_number']);
        $this->assertNotSame('ZERO_PATHS', $row['discovery_class']);
        $this->assertContains('NO_CATEGORY_PATH', $row['risks']);
        $this->assertContains('WEAK_DISCOVERY', $row['risks']);
        $this->assertSame([], $row['category_page_numbers']);
    }

    public function test_only_a_real_navigable_category_is_counted_as_category_path(): void
    {
        $category = Category::create(['name' => 'Discovery Category Test', 'slug' => 'discovery-category-test']);
        $article = $this->article('discovery-category-article', Article::STATUS_PUBLISHED, '<p>Body</p>', $category->slug);

        $row = app(ArticleDiscoveryAuditService::class)->audit()->firstWhere('article_id', $article->id);

        $this->assertSame(1, $row['category_page_numbers'][$category->slug]);
        $this->assertNotContains('NO_CATEGORY_PATH', $row['risks']);
    }

    public function test_public_percorso_prefix_and_real_body_incoming_link_are_counted_separately(): void
    {
        $target = $this->article('discovery-target', Article::STATUS_PUBLISHED);
        $this->article('discovery-source', Article::STATUS_PUBLISHED, '<p><a href="/articolo/discovery-target">Target</a></p>');
        $cluster = ContentCluster::create(['name' => 'Discovery Path Test', 'slug' => 'discovery-path-test', 'is_active' => true]);
        $cluster->articles()->attach($target->id, ['position' => 10, 'is_primary' => true]);

        $row = app(ArticleDiscoveryAuditService::class)->audit()->firstWhere('article_id', $target->id);

        $this->assertSame(1, $row['body_incoming_count']);
        $this->assertSame(1, $row['active_path_count']);
        $this->assertSame('MULTIPLE_PATHS', $row['discovery_class']);
        $this->assertContains('recommendations', $row['not_measured']);
        $this->assertNotContains('NO_BODY_INCOMING_LINKS', $row['risks']);
        $this->assertNotContains('WEAK_DISCOVERY', $row['risks']);
    }

    public function test_non_public_source_body_does_not_count_as_real_incoming_path(): void
    {
        $target = $this->article('discovery-public-target', Article::STATUS_PUBLISHED);
        $this->article('discovery-draft-source', Article::STATUS_DRAFT, '<p><a href="/articolo/discovery-public-target">Target</a></p>');
        $this->article('discovery-scheduled-source', Article::STATUS_SCHEDULED, '<p><a href="/articolo/discovery-public-target">Target</a></p>');

        $row = app(ArticleDiscoveryAuditService::class)->audit()->firstWhere('article_id', $target->id);

        $this->assertSame(0, $row['body_incoming_count']);
        $this->assertContains('NO_BODY_INCOMING_LINKS', $row['risks']);
    }

    private function article(string $slug, string $status, string $body = '<p>Body</p>', string $category = 'discovery-test'): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Excerpt',
            'body' => $body,
            'category' => $category,
            'status' => $status,
            'published_at' => match ($status) {
                Article::STATUS_PUBLISHED => now()->subMinute(),
                Article::STATUS_SCHEDULED => now()->addDay(),
                default => null,
            },
            'read_minutes' => 1,
        ]));
    }
}
