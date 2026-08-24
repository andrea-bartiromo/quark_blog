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
        // Confronto riga-persistita-contro-riga-persistita: getAttributes()
        // su un modello appena creato riflette solo le chiavi assegnate
        // esplicitamente (mai i default di colonna non toccati), quindi non
        // è un "prima" affidabile per un confronto "sola lettura" — un
        // confronto contro il fresh() successivo fallirebbe per colonne mai
        // scritte dal servizio, non per una mutazione reale.
        $before = $cluster->fresh()->getAttributes();

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

    /**
     * Mission 12 — Transition Text Health. TRANSITION_TEXT_GAPS's message
     * stays a generic count (public-safety contract, see
     * test_no_finding_message_ever_names_a_hidden_member_article below),
     * but the finding must also carry a 'detail' array identifying exactly
     * which non-terminal member(s) are missing a raccordo — this is what
     * lets the admin edit form flag the right rows instead of an editor
     * having to guess from a bare count.
     */
    public function test_transition_text_gaps_finding_carries_per_member_detail(): void
    {
        $first = $this->article('detail-first', Article::STATUS_PUBLISHED, now()->subDays(3));
        $second = $this->article('detail-second', Article::STATUS_PUBLISHED, now()->subDays(2));
        $terminal = $this->article('detail-terminal', Article::STATUS_PUBLISHED, now()->subDay());
        $cluster = $this->cluster('Transition Detail', $first);
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);
        $cluster->articles()->attach($second->id, ['position' => 20, 'is_primary' => false, 'transition_text' => 'Verso la terza tappa.']);
        $cluster->articles()->attach($terminal->id, ['position' => 30, 'is_primary' => false, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster->fresh());
        $finding = $report['findings']->firstWhere('code', 'TRANSITION_TEXT_GAPS');

        $this->assertNotNull($finding);
        $this->assertSame('1 raccordo/i tra tappe non terminali non sono compilati.', $finding['message']);
        $this->assertCount(1, $finding['detail']);
        $this->assertSame($first->id, $finding['detail'][0]['id']);
        $this->assertSame($first->title, $finding['detail'][0]['title']);
        $this->assertSame($first->slug, $finding['detail'][0]['slug']);
        $this->assertSame(10, $finding['detail'][0]['position']);
        // The terminal member (position 30) is never flagged even though
        // its own transition_text is also null — a terminal raccordo has
        // nothing left to introduce.
        $this->assertNotContains($terminal->id, collect($finding['detail'])->pluck('id')->all());
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

    /**
     * PERCORSI_READINESS_V2 completeness gap: a fully-configured multi-step
     * path with zero gaps and every editorial field filled must reach
     * READY with no findings at all — proving the service doesn't produce
     * false-positive warnings on a genuinely healthy Percorso.
     */
    public function test_fully_configured_multi_step_path_with_no_gaps_is_ready(): void
    {
        $first = $this->article('ready-first', Article::STATUS_PUBLISHED, now()->subDays(2));
        $second = $this->article('ready-second', Article::STATUS_PUBLISHED, now()->subDay());
        $third = $this->article('ready-third', Article::STATUS_PUBLISHED, now()->subHour());
        $cluster = $this->cluster('Fully Ready Path', $first);
        // ContentClusterHealth::PRIMARY_GAPS conta le membership globalmente
        // "primary": tutti e tre gli articoli appartengono solo a questo
        // Percorso, quindi ogni membership è legittimamente la loro unica
        // (e perciò primaria) casa — is_primary=true su tutte e tre non è
        // un artificio, è il fixture correttamente sano.
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Verso la seconda tappa.']);
        $cluster->articles()->attach($second->id, ['position' => 20, 'is_primary' => true, 'transition_text' => 'Verso la terza tappa.']);
        $cluster->articles()->attach($third->id, ['position' => 30, 'is_primary' => true, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster->fresh());

        $this->assertSame('READY', $report['status']);
        $this->assertSame('ACTIVE_IMMEDIATE_LEGACY', $report['publication_state']);
        $this->assertSame(3, $report['public_prefix_count']);
        // SCHEDULING_NOT_AVAILABLE è un finding INFO deliberato (mai
        // inventare uno stato scheduled inesistente): l'unico atteso su un
        // percorso altrimenti perfetto.
        $this->assertCount(1, $report['findings']);
        $this->assertSame('SCHEDULING_NOT_AVAILABLE', $report['findings']->first()['code']);
    }

    /**
     * A single-member path is READY WITH WARNINGS (SINGLE_MEMBER), never
     * NOT READY — the editorial advisory is not itself blocking.
     */
    public function test_single_member_path_is_ready_with_a_warning_not_blocked(): void
    {
        $only = $this->article('single-member', Article::STATUS_PUBLISHED, now()->subHour());
        $cluster = $this->cluster('Single Member Path', $only);
        $cluster->articles()->attach($only->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster->fresh());

        $this->assertSame('READY WITH WARNINGS', $report['status']);
        $this->assertTrue($report['findings']->contains('code', 'SINGLE_MEMBER'));
        $this->assertFalse($report['findings']->contains(fn ($finding) => $finding['severity'] === 'ERROR'));
    }

    /**
     * Public-safety regression: this service is admin/editorial-only, but a
     * hidden (scheduled/draft) member's title or slug must never leak into
     * a finding message even here — messages stay generic codes/labels,
     * never article identity, so nothing downstream can accidentally
     * surface it on a public surface by echoing a finding verbatim.
     */
    public function test_no_finding_message_ever_names_a_hidden_member_article(): void
    {
        $first = $this->article('leak-check-first', Article::STATUS_PUBLISHED, now()->subDays(2));
        $hidden = $this->article('leak-check-hidden-secret-title', Article::STATUS_SCHEDULED, now()->addDays(3));
        $cluster = $this->cluster('Leak Check Path', $first);
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Continua.']);
        $cluster->articles()->attach($hidden->id, ['position' => 20, 'is_primary' => false, 'transition_text' => null]);

        $report = app(PercorsoPublicationReadinessService::class)->evaluate($cluster->fresh());

        $this->assertTrue($report['findings']->contains('code', 'PUBLIC_SEQUENCE_BLOCKED'));
        foreach ($report['findings'] as $finding) {
            $this->assertStringNotContainsString($hidden->title, $finding['message']);
            $this->assertStringNotContainsString($hidden->slug, $finding['message']);
        }
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
