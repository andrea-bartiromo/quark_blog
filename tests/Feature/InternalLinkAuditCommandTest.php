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

    /**
     * Missione 43 (secondo batch autonomo KAIRUS, Fase E — Editorial
     * Quality & Readiness): "broken relationship safeguards". Ogni FK
     * coinvolta nel Content Graph/Percorsi è già cascade/set-null in
     * sicurezza (verificato missione per missione dalle migration), e
     * eliminare un articolo non lascia mai una riga orfana in nessuna
     * tabella. L'unico punto realmente scoperto era il caso di un link
     * REALE già inserito nel body pubblicato di un altro articolo verso
     * un articolo poi eliminato — mai testato esplicitamente prima
     * d'ora (solo il caso "slug mai esistito" lo era). Stesso identico
     * percorso di classificazione ('missing', nessun redirect creato
     * alla cancellazione — Article::booted() non lo fa), ma vale la pena
     * provarlo esplicitamente: è lo scenario editoriale reale
     * ("elimino un vecchio articolo" è un'azione normale), non solo
     * un caso limite sintattico.
     */
    public function test_a_link_to_an_article_that_was_since_deleted_is_classified_as_missing(): void
    {
        $target = $this->article(['slug' => 'articolo-da-eliminare']);
        $source = $this->article(['body' => '<p>Vedi <a href="/articolo/articolo-da-eliminare">questo</a>.</p>']);

        $target->delete();

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

    // ── Internal Linking V2.1: eleggibilità temporale scheduled→scheduled ──

    // 10. source scheduled -> target scheduled precedente => nessuna anomalia
    public function test_a_scheduled_source_linking_an_earlier_scheduled_target_is_classified_as_scheduled_safe(): void
    {
        $target = $this->article(['slug' => 'target-scheduled-precedente', 'status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-12 15:30:00']);
        $source = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-19 15:30:00',
            'body' => '<p>Vedi <a href="/articolo/target-scheduled-precedente">questo</a>.</p>',
        ]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame('scheduled_safe', $report->rows[0]->outgoingLinks[0]['classification']);
        $this->assertSame(0, $report->unpublishedTargets);
        $this->assertSame(1, $report->scheduledSafeLinks);
    }

    // 11. source scheduled -> target scheduled successivo => anomalia
    public function test_a_scheduled_source_linking_a_later_scheduled_target_is_classified_as_unpublished(): void
    {
        $target = $this->article(['slug' => 'target-scheduled-successivo', 'status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);
        $source = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-12 15:30:00',
            'body' => '<p>Vedi <a href="/articolo/target-scheduled-successivo">questo</a>.</p>',
        ]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame('unpublished', $report->rows[0]->outgoingLinks[0]['classification']);
        $this->assertSame(1, $report->unpublishedTargets);
        $this->assertSame(0, $report->scheduledSafeLinks);
    }

    /**
     * 12. Un target inizialmente sicuro (precedente) ma poi riprogrammato
     * DOPO la source: l'audit non è mai persistito (ricalcolato ad ogni
     * esecuzione, vedi InternalLinkAuditRow), quindi la nuova esecuzione
     * riflette automaticamente lo stato corrente senza bisogno di logica
     * dedicata al caso "riprogrammazione".
     */
    public function test_a_previously_safe_scheduled_target_becomes_an_anomaly_once_rescheduled_after_the_source(): void
    {
        $target = $this->article(['slug' => 'target-riprogrammato', 'status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-12 15:30:00']);
        $source = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-19 15:30:00',
            'body' => '<p>Vedi <a href="/articolo/target-riprogrammato">questo</a>.</p>',
        ]);

        $reportBefore = app(InternalLinkAuditService::class)->audit(articleId: $source->id);
        $this->assertSame('scheduled_safe', $reportBefore->rows[0]->outgoingLinks[0]['classification']);

        $target->update(['published_at' => '2026-08-25 15:30:00']);

        $reportAfter = app(InternalLinkAuditService::class)->audit(articleId: $source->id);
        $this->assertSame('unpublished', $reportAfter->rows[0]->outgoingLinks[0]['classification']);
        $this->assertSame(1, $reportAfter->unpublishedTargets);
    }

    /**
     * CASO 4 della missione: il target sicuro viene retrocesso a bozza (non
     * più 'scheduled') — deve diventare un'anomalia, stesso principio del
     * test precedente (stato corrente, mai mascherato).
     */
    public function test_a_previously_safe_scheduled_target_becomes_an_anomaly_once_demoted_to_draft(): void
    {
        $target = $this->article(['slug' => 'target-retrocesso', 'status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-12 15:30:00']);
        $source = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-19 15:30:00',
            'body' => '<p>Vedi <a href="/articolo/target-retrocesso">questo</a>.</p>',
        ]);

        $target->update(['status' => Article::STATUS_DRAFT, 'published_at' => null]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame('unpublished', $report->rows[0]->outgoingLinks[0]['classification']);
    }

    /**
     * 13 / CASO 5: la source viene pubblicata manualmente in anticipo
     * mentre il target è ancora scheduled — poiché la source è ORA
     * pubblica, l'eccezione scheduled→scheduled non si applica più: il
     * target non pubblico deve essere segnalato.
     */
    public function test_a_source_published_early_while_the_target_is_still_scheduled_is_classified_as_unpublished(): void
    {
        $target = $this->article(['slug' => 'target-ancora-scheduled', 'status' => Article::STATUS_SCHEDULED, 'published_at' => '2026-08-19 15:30:00']);
        $source = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-12 15:30:00',
            'body' => '<p>Vedi <a href="/articolo/target-ancora-scheduled">questo</a>.</p>',
        ]);

        // La source era temporalmente sicura verso questo target finché
        // era scheduled 12/08 -> scheduled 19/08 (12 < 19): ma qui viene
        // pubblicata a mano prima del previsto.
        $source->update(['status' => Article::STATUS_PUBLISHED]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame('unpublished', $report->rows[0]->outgoingLinks[0]['classification']);
        $this->assertSame(1, $report->unpublishedTargets);
    }

    // 15. Nessuna regressione sui target già pubblicati, anche da una source scheduled
    public function test_a_scheduled_source_linking_an_already_published_target_is_still_classified_as_valid(): void
    {
        $target = $this->article(['slug' => 'target-gia-pubblicato']);
        $source = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
            'body' => '<p>Vedi <a href="/articolo/target-gia-pubblicato">questo</a>.</p>',
        ]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame('valid', $report->rows[0]->outgoingLinks[0]['classification']);
        $this->assertSame(0, $report->unpublishedTargets);
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

    /**
     * Regressione Codex (PR #158, P2): un articolo con status 'published'
     * ma published_at nel futuro non è raggiungibile dal pubblico —
     * Article::scopePublished() (usato da ArticleController::show()) lo
     * esclude. Un link verso quel target deve essere 'unpublished', non
     * 'valid': il lettore riceverebbe comunque un 404.
     */
    public function test_a_link_to_a_published_status_article_with_a_future_published_at_is_classified_as_unpublished(): void
    {
        $target = $this->article(['slug' => 'pubblicato-nel-futuro', 'published_at' => now()->addDays(3)]);
        $source = $this->article(['body' => '<p>Vedi <a href="/articolo/pubblicato-nel-futuro">questo</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame('unpublished', $report->rows[0]->outgoingLinks[0]['classification']);
        $this->assertSame(1, $report->unpublishedTargets);
        $this->assertSame(0, $report->brokenLinks);
    }

    /**
     * Regressione Codex (PR #158, P2): un redirect che risolve a un target
     * NON pubblicamente visibile (es. retrocesso a bozza dopo la
     * rinomina) darebbe comunque 404 al lettore — non deve essere
     * classificato 'redirected' come se funzionasse.
     */
    public function test_a_redirect_resolving_to_a_non_public_target_is_classified_as_unpublished_not_redirected(): void
    {
        $target = $this->article(['slug' => 'ora-e-una-bozza', 'status' => Article::STATUS_DRAFT, 'published_at' => null]);
        ArticleSlugRedirect::create(['old_slug' => 'vecchio-slug-bozza', 'article_id' => $target->id]);
        $source = $this->article(['body' => '<p>Vedi <a href="/articolo/vecchio-slug-bozza">questo</a>.</p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame('unpublished', $report->rows[0]->outgoingLinks[0]['classification']);
        $this->assertSame(0, $report->redirectedLinks);
    }

    /**
     * Regressione Codex (PR #158, P2): un articolo con incoming link solo
     * tramite un redirect verso un target non pubblico non deve "salvarlo"
     * dall'essere considerato isolato — l'incoming count rispetta la
     * stessa visibilità pubblica della classificazione.
     */
    public function test_incoming_links_count_never_credits_a_link_resolving_to_a_non_public_target(): void
    {
        $orphanButLinked = $this->article(['slug' => 'sembra-collegato', 'status' => Article::STATUS_DRAFT, 'published_at' => null]);
        $this->article(['body' => '<p><a href="/articolo/sembra-collegato">link</a></p>']);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $orphanButLinked->id);

        $this->assertSame(0, $report->rows[0]->incomingLinksCount);
    }

    /**
     * Regressione Codex (PR #158, P2): un articolo che collega la stessa
     * destinazione due volte — una tramite lo slug corrente, una tramite
     * un vecchio slug ora reindirizzato — collega UN solo articolo
     * distinto, non due. Prima del fix questo gonfiava
     * outgoingDistinctCount (e la sua classificazione in "con 2+ link").
     */
    public function test_linking_the_same_target_via_its_current_slug_and_an_old_redirected_slug_counts_as_one_outgoing_link(): void
    {
        $target = $this->article(['slug' => 'slug-attuale']);
        ArticleSlugRedirect::create(['old_slug' => 'slug-vecchio', 'article_id' => $target->id]);
        $source = $this->article([
            'body' => '<p><a href="/articolo/slug-attuale">nuovo</a> e anche <a href="/articolo/slug-vecchio">vecchio</a></p>',
        ]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(1, $report->rows[0]->outgoingDistinctCount);
        $this->assertSame(1, $report->withOneOutgoingLink);
        $this->assertSame(0, $report->withTwoOrMoreOutgoingLinks);
    }

    /**
     * Contrasto con il test precedente: due link ROTTI (slug inesistenti,
     * nessuna risoluzione) restano distinti tra loro se sono slug diversi
     * — la deduplicazione riguarda solo identità RISOLTE, non "qualunque
     * link rotto conta come uno".
     */
    public function test_two_distinct_broken_links_are_never_merged_into_one_outgoing_link(): void
    {
        $source = $this->article([
            'body' => '<p><a href="/articolo/rotto-uno">a</a> e <a href="/articolo/rotto-due">b</a></p>',
        ]);

        $report = app(InternalLinkAuditService::class)->audit(articleId: $source->id);

        $this->assertSame(2, $report->rows[0]->outgoingDistinctCount);
        $this->assertSame(2, $report->brokenLinks);
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

    // Codex (PR #165, round 12): un suggerimento 'proposed' resta tale a
    // database anche dopo che il target è stato riprogrammato DOPO la
    // sorgente (l'unica revalidazione avviene al salvataggio della
    // sorgente, mai qui) — l'audit non deve segnalarlo come opportunità
    // reale, esattamente come l'editor di $source non lo mostra più (vedi
    // Article::proposedLinkSuggestions()).
    public function test_a_proposed_suggestion_whose_target_became_temporally_unsafe_never_appears_as_an_opportunity(): void
    {
        $source = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(2),
        ]);

        $target = $this->article([
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(5),
        ]);

        ArticleLinkSuggestion::create([
            'source_article_id' => $source->id,
            'target_article_id' => $target->id,
            'anchor_text' => 'termine specifico',
            'reason' => 'motivo',
            'confidence_score' => 85,
            'status' => ArticleLinkSuggestion::STATUS_PROPOSED,
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
