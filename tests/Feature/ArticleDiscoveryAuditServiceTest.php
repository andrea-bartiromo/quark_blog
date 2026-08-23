<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\Discovery\ArticleDiscoveryAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleDiscoveryAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_articles_are_audited_and_archive_is_a_real_path(): void
    {
        $published = $this->article('discovery-published', Article::STATUS_PUBLISHED);
        $this->article('discovery-draft', Article::STATUS_DRAFT);
        $this->article('discovery-scheduled', Article::STATUS_SCHEDULED);

        $rows = app(ArticleDiscoveryAuditService::class)->audit();

        $this->assertSame([$published->id], $rows->pluck('article_id')->all());
        $this->assertSame(1, $rows->first()['archive_page_number']);
        $this->assertNotSame('ZERO_PATHS', $rows->first()['discovery_class']);
    }

    public function test_active_percorso_and_real_body_incoming_link_are_counted_separately(): void
    {
        $target = $this->article('discovery-target', Article::STATUS_PUBLISHED);
        $source = $this->article('discovery-source', Article::STATUS_PUBLISHED, '<p><a href="/articolo/discovery-target">Target</a></p>');
        $cluster = ContentCluster::create(['name' => 'Discovery Path Test', 'slug' => 'discovery-path-test', 'is_active' => true]);
        $cluster->articles()->attach($target->id, ['position' => 1, 'is_primary' => true]);

        $row = app(ArticleDiscoveryAuditService::class)->audit()->firstWhere('article_id', $target->id);

        $this->assertSame(1, $row['body_incoming_count']);
        $this->assertSame(1, $row['active_path_count']);
        $this->assertSame('MULTIPLE_PATHS', $row['discovery_class']);
        $this->assertContains('recommendations', $row['not_measured']);
        $this->assertNotContains('NO_BODY_INCOMING_LINKS', $row['risks']);
    }

    private function article(string $slug, string $status, string $body = '<p>Body</p>'): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Excerpt',
            'body' => $body,
            'category' => 'discovery-test',
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
