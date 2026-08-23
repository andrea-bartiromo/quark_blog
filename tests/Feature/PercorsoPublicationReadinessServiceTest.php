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

        $this->assertSame('NOT READY', $report['status']);
        $this->assertTrue($report['findings']->contains('code', 'SHORT_DESCRIPTION_MISSING'));
        $this->assertTrue($report['findings']->contains('code', 'HEALTH_NO_PILLAR'));
        $this->assertTrue($report['findings']->contains('code', 'SCHEDULING_NOT_AVAILABLE'));
        $this->assertSame($before, $cluster->fresh()->getAttributes());
    }

    public function test_future_publication_date_errors_when_no_member_or_pillar_is_available_by_then(): void
    {
        $article = $this->article('readiness-future-article', Article::STATUS_SCHEDULED, now()->addDays(5));
        $cluster = $this->cluster('Readiness Future', $article);
        $cluster->articles()->attach($article->id, ['position' => 1, 'is_primary' => true, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster, now()->addDay());

        $this->assertSame('NOT READY', $report['status']);
        $this->assertTrue($report['findings']->contains('code', 'NO_MEMBERS_AVAILABLE_AT_PUBLICATION'));
        $this->assertTrue($report['findings']->contains('code', 'PILLAR_UNAVAILABLE_AT_PUBLICATION'));
    }

    public function test_scheduled_pillar_and_member_before_path_date_are_not_rejected_for_not_being_public_now(): void
    {
        $article = $this->article('readiness-before-path', Article::STATUS_SCHEDULED, now()->addDay());
        $cluster = $this->cluster('Readiness Scheduled Before', $article);
        $cluster->articles()->attach($article->id, ['position' => 1, 'is_primary' => true, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster, now()->addDays(2));

        $this->assertFalse($report['findings']->contains('code', 'HEALTH_NO_PUBLIC_ARTICLES'));
        $this->assertFalse($report['findings']->contains('code', 'HEALTH_PILLAR_NOT_PUBLIC'));
        $this->assertFalse($report['findings']->contains('code', 'NO_MEMBERS_AVAILABLE_AT_PUBLICATION'));
        $this->assertFalse($report['findings']->contains('code', 'PILLAR_UNAVAILABLE_AT_PUBLICATION'));
    }

    public function test_member_after_path_date_is_a_warning_when_another_member_is_available(): void
    {
        $pillar = $this->article('readiness-pillar-before', Article::STATUS_SCHEDULED, now()->addDay());
        $late = $this->article('readiness-member-after', Article::STATUS_SCHEDULED, now()->addDays(5));
        $cluster = $this->cluster('Readiness Mixed Members', $pillar);
        $cluster->articles()->attach($pillar->id, ['position' => 1, 'is_primary' => true, 'transition_text' => null]);
        $cluster->articles()->attach($late->id, ['position' => 2, 'is_primary' => false, 'transition_text' => 'Poi approfondiamo.']);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster, now()->addDays(2));

        $this->assertTrue($report['findings']->contains('code', 'MEMBERS_UNAVAILABLE_AT_PUBLICATION'));
        $this->assertFalse($report['findings']->contains('code', 'NO_MEMBERS_AVAILABLE_AT_PUBLICATION'));
        $this->assertFalse($report['findings']->contains('code', 'PILLAR_UNAVAILABLE_AT_PUBLICATION'));
    }

    private function cluster(string $name, Article $pillar): ContentCluster
    {
        return ContentCluster::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'short_description' => 'Breve',
            'description' => 'Descrizione',
            'seo_title' => 'SEO title',
            'seo_description' => 'SEO description',
            'cover_image' => 'test.jpg',
            'takeaways' => ['Takeaway'],
            'guiding_questions' => ['Domanda?'],
            'closing_text' => 'Conclusione',
            'curator_note' => 'Nota',
            'is_active' => false,
            'pillar_article_id' => $pillar->id,
        ]);
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
