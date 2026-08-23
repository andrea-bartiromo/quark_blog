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

    public function test_readiness_is_warning_first_read_only_and_honest_about_missing_scheduling_runtime(): void
    {
        $cluster = ContentCluster::create([
            'name' => 'Readiness Test',
            'slug' => 'readiness-test',
            'is_active' => false,
        ]);
        $before = $cluster->getAttributes();

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster);

        $this->assertSame('NOT READY', $report['status']);
        $this->assertSame('INACTIVE', $report['publication_state']);
        $this->assertTrue($report['findings']->contains('code', 'SHORT_DESCRIPTION_MISSING'));
        $this->assertTrue($report['findings']->contains('code', 'HEALTH_NO_PILLAR'));
        $this->assertTrue($report['findings']->contains('code', 'SCHEDULING_NOT_AVAILABLE'));
        $this->assertSame($before, $cluster->fresh()->getAttributes());
    }

    public function test_future_publication_errors_when_first_member_and_pillar_are_not_available_by_then(): void
    {
        $article = $this->article('readiness-future-article', Article::STATUS_SCHEDULED, now()->addDays(5));
        $cluster = $this->cluster('Readiness Future', $article);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster, now()->addDay());

        $this->assertSame('NOT READY', $report['status']);
        $this->assertSame(0, $report['public_prefix_count']);
        $this->assertTrue($report['findings']->contains('code', 'NO_MEMBERS_AVAILABLE_AT_PUBLICATION'));
        $this->assertTrue($report['findings']->contains('code', 'PILLAR_UNAVAILABLE_AT_PUBLICATION'));
    }

    public function test_scheduled_pillar_and_member_before_path_date_are_in_the_future_public_prefix(): void
    {
        $article = $this->article('readiness-before-path', Article::STATUS_SCHEDULED, now()->addDay());
        $cluster = $this->cluster('Readiness Scheduled Before', $article);
        $cluster->articles()->attach($article->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster, now()->addDays(2));

        $this->assertSame(1, $report['public_prefix_count']);
        $this->assertFalse($report['findings']->contains('code', 'HEALTH_NO_PUBLIC_ARTICLES'));
        $this->assertFalse($report['findings']->contains('code', 'HEALTH_PILLAR_NOT_PUBLIC'));
        $this->assertFalse($report['findings']->contains('code', 'NO_MEMBERS_AVAILABLE_AT_PUBLICATION'));
        $this->assertFalse($report['findings']->contains('code', 'PILLAR_UNAVAILABLE_AT_PUBLICATION'));
    }

    public function test_member_after_path_date_blocks_the_future_prefix_without_exposing_later_available_member(): void
    {
        $pillar = $this->article('readiness-pillar-before', Article::STATUS_SCHEDULED, now()->addDay());
        $late = $this->article('readiness-member-after', Article::STATUS_SCHEDULED, now()->addDays(5));
        $alreadyAvailableLater = $this->article('readiness-later-published', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = $this->cluster('Readiness Mixed Members', $pillar);
        $cluster->articles()->attach($pillar->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Poi approfondiamo.']);
        $cluster->articles()->attach($late->id, ['position' => 20, 'is_primary' => false, 'transition_text' => 'Poi continuiamo.']);
        $cluster->articles()->attach($alreadyAvailableLater->id, ['position' => 30, 'is_primary' => false, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster, now()->addDays(2));

        $this->assertSame(1, $report['public_prefix_count']);
        $this->assertTrue($report['findings']->contains('code', 'PUBLIC_SEQUENCE_BLOCKED_AT_PUBLICATION'));
        $this->assertFalse($report['findings']->contains('code', 'NO_MEMBERS_AVAILABLE_AT_PUBLICATION'));
        $this->assertFalse($report['findings']->contains('code', 'PILLAR_UNAVAILABLE_AT_PUBLICATION'));
    }

    public function test_terminal_transition_null_is_valid_and_missing_non_terminal_transition_is_a_warning(): void
    {
        $first = $this->article('transition-first', Article::STATUS_PUBLISHED, now()->subDays(2));
        $second = $this->article('transition-terminal', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = $this->cluster('Transition Semantics', $first);
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);
        $cluster->articles()->attach($second->id, ['position' => 20, 'is_primary' => false, 'transition_text' => null]);

        $missing = app(PercorsoPublicationReadinessService::class)->evaluate($cluster);
        $this->assertTrue($missing['findings']->contains('code', 'TRANSITION_TEXT_GAPS'));

        $cluster->articles()->updateExistingPivot($first->id, ['transition_text' => 'Verso la seconda tappa.']);
        $complete = app(PercorsoPublicationReadinessService::class)->evaluate($cluster->fresh());

        $this->assertFalse($complete['findings']->contains('code', 'TRANSITION_TEXT_GAPS'));
    }

    public function test_published_pillar_beyond_current_gap_is_not_readiness_safe(): void
    {
        $first = $this->article('prefix-first', Article::STATUS_PUBLISHED, now()->subDays(2));
        $gap = $this->article('prefix-gap', Article::STATUS_SCHEDULED, now()->addDays(2));
        $pillarBeyondGap = $this->article('prefix-pillar-later', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = $this->cluster('Pillar Beyond Gap', $pillarBeyondGap);
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Continua.']);
        $cluster->articles()->attach($gap->id, ['position' => 20, 'is_primary' => false, 'transition_text' => 'Poi.']);
        $cluster->articles()->attach($pillarBeyondGap->id, ['position' => 30, 'is_primary' => false, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster);

        $this->assertSame(1, $report['public_prefix_count']);
        $this->assertTrue($report['findings']->contains('code', 'PUBLIC_SEQUENCE_BLOCKED'));
        $this->assertTrue($report['findings']->contains('code', 'PILLAR_OUTSIDE_PUBLIC_PREFIX'));
        $this->assertSame('NOT READY', $report['status']);
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
            'is_active' => true,
            'lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE,
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
