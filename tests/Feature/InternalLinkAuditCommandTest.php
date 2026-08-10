<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\ArticleSlugRedirect;
use App\Models\User;
use App\Services\InternalLinking\InternalLinkAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InternalLinkAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'editor'])->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Un breve sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ], $overrides));
    }

    // ── Classificazione dei link uscenti ──

    public function test_a_link_to_a_published_article_is_classified_as_valid(): void
    {
        $target = $this->article(['slug' => 'target-valido']);
        $source = $this->article(['body' => '<p>Vedi <a href="/articolo/target-valido">questo articolo</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(0, $report->brokenLinks);
        $this->assertSame(1, $report->rows[0]->outgoingDistinctCount);
        $this->assertSame('valid', $report->rows[0]->outgoingLinks[0]['classification']);
    }

    public function test_a_link_to_a_nonexistent_slug_is_classified_as_missing(): void
    {
        $source = $this->article(['body' => '<p>Vedi <a href="/articolo/non-esiste-questo-slug">questo</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(1, $report->brokenLinks);
        $this->assertSame('missing', $report->rows[0]->outgoingLinks[0]['classification']);
    }

    public function test_a_link_to_a_draft_article_is_classified_as_unpublished(): void
    {
        $draftTarget = $this->article(['slug' => 'ancora-bozza', 'status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $source = $this->article(['body' => '<p>Vedi <a href="/articolo/ancora-bozza">questo</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(1, $report->unpublishedTargets);
        $this->assertSame('unpublished', $report->rows[0]->outgoingLinks[0]['classification']);
    }

    public function test_a_self_link_is_classified_as_self(): void
    {
        $source = $this->article(['slug' => 'si-collega-da-solo']);
        $source->update(['body' => '<p>Vedi <a href="/articolo/si-collega-da-solo">questo stesso articolo</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(1, $report->selfLinks);
        $this->assertSame('self', $report->rows[0]->outgoingLinks[0]['classification']);
    }

    public function test_a_link_to_a_redirected_old_slug_is_classified_as_redirected(): void
    {
        $target = $this->article(['slug' => 'nuovo-slug']);
        ArticleSlugRedirect::create(['old_slug' => 'vecchio-slug', 'article_id' => $target->id]);
        $source = $this->article(['body' => '<p>Vedi <a href="/articolo/vecchio-slug">questo</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(1, $report->redirectedLinks);
        $this->assertSame(0, $report->brokenLinks);
        $this->assertSame('redirected', $report->rows[0]->outgoingLinks[0]['classification']);
    }

    public function test_an_external_link_is_never_counted_as_an_internal_article_link(): void
    {
        $source = $this->article(['body' => '<p>Vedi <a href="https://example.com/pagina">questo sito esterno</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(0, $report->rows[0]->outgoingDistinctCount);
        $this->assertSame([], $report->rows[0]->outgoingLinks);
    }

    // ── Incoming links / articoli isolati ──

    public function test_incoming_links_count_is_computed_across_the_whole_corpus_even_when_filtering_a_single_article(): void
    {
        $target = $this->article(['slug' => 'molto-collegato']);
        $this->article(['body' => '<p><a href="/articolo/molto-collegato">link 1</a></p>']);
        $this->article(['body' => '<p><a href="/articolo/molto-collegato">link 2</a></p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $target->id);

        $this->assertSame(2, $report->rows[0]->incomingLinksCount);
        $this->assertFalse($report->rows[0]->isOrphan());
    }

    public function test_the_same_source_linking_twice_to_the_same_target_counts_as_one_incoming_reference(): void
    {
        $target = $this->article(['slug' => 'doppio-link-stesso-sorgente']);
        $this->article(['body' => '<p><a href="/articolo/doppio-link-stesso-sorgente">a</a> e ancora <a href="/articolo/doppio-link-stesso-sorgente">b</a></p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $target->id);

        $this->assertSame(1, $report->rows[0]->incomingLinksCount);
    }

    public function test_a_published_article_with_zero_incoming_links_is_isolated(): void
    {
        $orphan = $this->article();

        $report = app(InternalLinkAuditService::class)->audit(articleId: $orphan->id);

        $this->assertTrue($report->rows[0]->isOrphan());
        $this->assertSame(1, $report->isolatedArticles);
    }

    public function test_a_draft_article_with_zero_incoming_links_is_never_reported_as_isolated(): void
    {
        $draft = $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $draft->id);

        $this->assertFalse($report->rows[0]->isOrphan());
        $this->assertSame(0, $report->isolatedArticles);
    }

    // ── Anchor ambigui ──

    public function test_the_same_anchor_text_pointing_to_two_different_targets_is_flagged_as_ambiguous(): void
    {
        $this->article(['slug' => 'destinazione-a']);
        $this->article(['slug' => 'destinazione-b']);
        $source = $this->article([
            'body' => '<p><a href="/articolo/destinazione-a">Scopri di più</a> e anche <a href="/articolo/destinazione-b">Scopri di più</a>.</p>',
        ]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertTrue($report->rows[0]->hasAmbiguousAnchor);
        $this->assertSame(1, $report->articlesWithAmbiguousAnchors);
    }

    public function test_the_same_anchor_text_pointing_to_the_same_target_twice_is_never_flagged_as_ambiguous(): void
    {
        $this->article(['slug' => 'stessa-destinazione']);
        $source = $this->article([
            'body' => '<p><a href="/articolo/stessa-destinazione">Scopri di più</a> e ancora <a href="/articolo/stessa-destinazione">Scopri di più</a>.</p>',
        ]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertFalse($report->rows[0]->hasAmbiguousAnchor);
    }

    // ── Filtri ──

    public function test_status_filter_limits_the_analyzed_articles(): void
    {
        $this->article(['status' => Article::STATUS_PUBLISHED]);
        $this->article(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $report = app(InternalLinkAuditService::class)->audit(status: Article::STATUS_DRAFT);

        $this->assertSame(1, $report->analyzed);
        $this->assertSame(Article::STATUS_DRAFT, $report->rows[0]->status);
    }

    // ── Top opportunità ──

    public function test_a_scheduled_article_without_any_internal_link_appears_in_the_opportunities(): void
    {
        $scheduled = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $report = app(InternalLinkAuditService::class)->audit();

        $this->assertSame([
            ['id' => $scheduled->id, 'title' => $scheduled->title, 'slug' => $scheduled->slug],
        ], $report->scheduledWithoutInternalLinks);
    }

    public function test_a_high_confidence_proposed_suggestion_appears_in_the_opportunities(): void
    {
        $source = $this->article();
        $target = $this->article();

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine specifico',
            'reason' => 'motivo',
            'confidence_score' => 85,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ]);

        $report = app(InternalLinkAuditService::class)->audit();

        $this->assertCount(1, $report->highConfidenceUnusedSuggestions);
        $this->assertSame(85, $report->highConfidenceUnusedSuggestions[0]['confidence_score']);
    }

    public function test_a_low_confidence_proposed_suggestion_never_appears_in_the_opportunities(): void
    {
        $source = $this->article();
        $target = $this->article();

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine debole',
            'reason' => 'motivo',
            'confidence_score' => 45,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
        ]);

        $report = app(InternalLinkAuditService::class)->audit();

        $this->assertSame([], $report->highConfidenceUnusedSuggestions);
    }

    public function test_an_already_accepted_suggestion_never_appears_as_an_opportunity(): void
    {
        $source = $this->article();
        $target = $this->article();

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine specifico',
            'reason' => 'motivo',
            'confidence_score' => 90,
            'status' => ArticleLinkSuggestion::STATUS_ACCEPTED,
        ]);

        $report = app(InternalLinkAuditService::class)->audit();

        $this->assertSame([], $report->highConfidenceUnusedSuggestions);
    }

    // ── Comando: read-only, JSON, testo ──

    public function test_the_command_never_modifies_any_article(): void
    {
        $article = $this->article(['body' => '<p><a href="/articolo/non-esiste">rotto</a></p>']);
        $originalUpdatedAt = $article->updated_at;
        $originalBody = $article->body;
        $originalStatus = $article->status;

        $this->artisan('content:internal-link-audit')->assertExitCode(0);

        $article->refresh();

        $this->assertEquals($originalUpdatedAt, $article->updated_at);
        $this->assertSame($originalBody, $article->body);
        $this->assertSame($originalStatus, $article->status);
    }

    public function test_the_command_prints_a_text_report_by_default(): void
    {
        $this->article();

        $this->artisan('content:internal-link-audit')
            ->expectsOutputToContain('INTERNAL LINK AUDIT — KAIRUS')
            ->assertExitCode(0);
    }

    public function test_the_json_output_is_valid_and_contains_the_expected_shape(): void
    {
        $this->article();

        $exitCode = Artisan::call('content:internal-link-audit', ['--json' => true]);
        $output = Artisan::output();
        $decoded = json_decode($output, true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('articles', $decoded);
        $this->assertArrayHasKey('top_opportunities', $decoded);
        $this->assertArrayNotHasKey('body', $decoded['articles'][0] ?? []);
    }

    public function test_article_filter_targets_only_that_article(): void
    {
        $a = $this->article();
        $this->article();

        $this->artisan('content:internal-link-audit', ['--article' => $a->id])->assertExitCode(0);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $a->id);
        $this->assertSame(1, $report->analyzed);
    }
}
