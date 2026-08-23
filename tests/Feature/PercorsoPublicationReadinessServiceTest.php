<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusters\PercorsoPublicationReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PercorsoPublicationReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_is_warning_first_and_does_not_mutate_the_cluster(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Readiness Test',
            'slug' => 'readiness-test',
            'is_active' => false,
        ]);
        $before = $cluster->getAttributes();

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster);

        $this->assertContains($report['status'], ['ERROR', 'WARNING']);
        $this->assertTrue($report['findings']->contains('code', 'SHORT_DESCRIPTION_MISSING'));
        $this->assertTrue($report['findings']->contains('code', 'SCHEDULING_NOT_AVAILABLE'));
        $this->assertSame($before, $cluster->fresh()->getAttributes());
    }

    public function test_future_publication_date_warns_when_members_or_pillar_are_not_available_by_then(): void
    {
        $article = $this->article('readiness-future-article', Article::STATUS_SCHEDULED, now()->addDays(5));
        $cluster = ContentCluster::create([
            'name' => 'Readiness Future',
            'slug' => 'readiness-future',
            'short_description' => 'Breve',
            'description' => 'Descrizione',
            'is_active' => false,
            'pillar_article_id' => $article->id,
        ]);
        $cluster->articles()->attach($article->id, ['position' => 1, 'is_primary' => true, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster, now()->addDay());

        $this->assertTrue($report['findings']->contains('code', 'MEMBERS_UNAVAILABLE_AT_PUBLICATION'));
        $this->assertTrue($report['findings']->contains('code', 'PILLAR_UNAVAILABLE_AT_PUBLICATION'));
    }

    private function article(string $slug, string $status, $publishedAt): Article
    {
        return Article::withoutEvents(fn () => Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'excerpt' => 'Excerpt',
            'body' => '<p>Body</p>',
            'category' => 'readiness-test',
            'status' => $status,
            'published_at' => $publishedAt,
            'read_minutes' => 1,
        ]));
    }
}
