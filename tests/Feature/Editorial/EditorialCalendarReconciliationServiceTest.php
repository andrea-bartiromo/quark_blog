<?php

namespace Tests\Feature\Editorial;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\Editorial\EditorialCalendarMatchingService;
use App\Services\Editorial\EditorialCalendarReconciliationEntry;
use App\Services\Editorial\EditorialCalendarReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialCalendarReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EditorialCalendarReconciliationService
    {
        return app(EditorialCalendarReconciliationService::class);
    }

    private function project(): Project
    {
        return Project::factory()->create();
    }

    private function calendarDocument(Project $project, string $content): ProjectDocument
    {
        return ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'content' => $content,
            'is_editorial_calendar' => true,
        ]);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ], $overrides));
    }

    // ── Nessun documento calendario ─────────────────────────────────────

    public function test_a_project_without_a_calendar_document_produces_an_empty_report(): void
    {
        $project = $this->project();

        $report = $this->service()->reconcile($project);

        $this->assertNull($report->documentId);
        $this->assertSame(0, $report->totalEntries());
        $this->assertCount(0, $report->parseErrors);
        $this->assertCount(0, $report->articlesOutsidePlan);
    }

    // ── Match esatto / normalizzato ──────────────────────────────────────

    public function test_an_exact_title_match_has_no_discrepancy(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Perché il cielo è nero?']);
        $document = $this->calendarDocument($project, "28/08/2026 — Perché il cielo è nero?\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame($document->id, $report->documentId);
        $this->assertCount(1, $report->entries);
        $this->assertSame(EditorialCalendarMatchingService::MATCH_EXACT, $report->entries[0]->match->matchType);
        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, $report->entries[0]->discrepancyType);
    }

    public function test_a_normalized_title_match_is_a_minor_title_change(): void
    {
        $project = $this->project();
        $this->article(['title' => 'perché il cielo è nero']);
        $this->calendarDocument($project, "28/08/2026 — Perché il cielo è nero?\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(
            EditorialCalendarReconciliationEntry::DISCREPANCY_TITLE_MINOR_CHANGE,
            $report->entries[0]->discrepancyType
        );
    }

    // ── Match ambiguo ─────────────────────────────────────────────────

    public function test_an_ambiguous_match_with_a_single_candidate_is_a_major_title_change(): void
    {
        $project = $this->project();
        $this->article(['title' => 'GPT-5 e il futuro del lavoro in Italia']);
        $this->calendarDocument(
            $project,
            "28/08/2026 — GPT-5 e il futuro del lavoro: quali professioni sopravvivono\n"
        );

        $report = $this->service()->reconcile($project);

        $entry = $report->entries[0];
        if ($entry->match->matchType === EditorialCalendarMatchingService::MATCH_AMBIGUOUS) {
            $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_TITLE_MAJOR_CHANGE, $entry->discrepancyType);
        } else {
            $this->assertSame(EditorialCalendarMatchingService::MATCH_NONE, $entry->match->matchType);
            $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_MISSING_ARTICLE, $entry->discrepancyType);
        }
    }

    public function test_an_ambiguous_match_with_multiple_candidates_requires_review(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Titolo duplicato']);
        $this->article(['title' => 'Titolo duplicato']);
        $this->calendarDocument($project, "28/08/2026 — Titolo duplicato\n");

        $report = $this->service()->reconcile($project);

        $entry = $report->entries[0];
        $this->assertSame(EditorialCalendarMatchingService::MATCH_AMBIGUOUS, $entry->match->matchType);
        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_REQUIRES_REVIEW, $entry->discrepancyType);
        $this->assertContains($entry, $report->requiringReview());
    }

    // ── Nessun match ──────────────────────────────────────────────────

    public function test_no_matching_article_is_a_missing_article_discrepancy(): void
    {
        $project = $this->project();
        $this->calendarDocument($project, "28/08/2026 — Un titolo che non esiste nel CMS\n");

        $report = $this->service()->reconcile($project);

        $entry = $report->entries[0];
        $this->assertSame(EditorialCalendarMatchingService::MATCH_NONE, $entry->match->matchType);
        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_MISSING_ARTICLE, $entry->discrepancyType);
        $this->assertContains($entry, $report->missingArticles());
    }

    // ── Discrepanze di data ───────────────────────────────────────────

    public function test_a_published_article_earlier_than_planned_is_a_date_early_discrepancy(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo pubblicato in anticipo',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => '2026-08-20 10:00:00',
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo pubblicato in anticipo\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_DATE_EARLY, $report->entries[0]->discrepancyType);
        $this->assertContains($report->entries[0], $report->dateDiscrepancies());
    }

    public function test_a_published_article_later_than_planned_is_a_date_late_discrepancy(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo pubblicato in ritardo',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => '2026-09-05 10:00:00',
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo pubblicato in ritardo\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_DATE_LATE, $report->entries[0]->discrepancyType);
    }

    public function test_a_matching_planned_date_and_real_date_has_no_date_discrepancy(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo perfettamente allineato',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-28 09:00:00',
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo perfettamente allineato\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, $report->entries[0]->discrepancyType);
    }

    public function test_a_draft_article_with_no_real_date_is_not_a_date_discrepancy(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Titolo ancora bozza', 'status' => Article::STATUS_DRAFT]);
        $this->calendarDocument($project, "28/08/2026 — Titolo ancora bozza\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, $report->entries[0]->discrepancyType);
    }

    // ── Discrepanze di stato ─────────────────────────────────────────

    public function test_a_recognized_declared_status_mismatching_the_real_status_is_flagged(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo con stato in conflitto',
            'status' => Article::STATUS_DRAFT,
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo con stato in conflitto [pubblicato]\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_STATUS_MISMATCH, $report->entries[0]->discrepancyType);
    }

    public function test_an_unrecognized_declared_status_text_never_produces_a_false_mismatch(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo con stato non riconosciuto',
            'status' => Article::STATUS_DRAFT,
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo con stato non riconosciuto [???]\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(EditorialCalendarReconciliationEntry::DISCREPANCY_NONE, $report->entries[0]->discrepancyType);
    }

    // ── articlesOutsidePlan ───────────────────────────────────────────

    public function test_a_linked_article_matched_by_the_calendar_is_not_outside_the_plan(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo collegato e pianificato']);
        $project->articles()->attach($article->id);
        $this->calendarDocument($project, "28/08/2026 — Titolo collegato e pianificato\n");

        $report = $this->service()->reconcile($project);

        $this->assertCount(0, $report->articlesOutsidePlan);
        $this->assertTrue($report->entries[0]->match->alreadyLinkedToProject);
        $this->assertCount(1, $report->alreadyLinked());
    }

    public function test_a_linked_article_not_matched_by_any_entry_is_outside_the_plan(): void
    {
        $project = $this->project();
        $linkedArticle = $this->article(['title' => 'Articolo collegato ma non nel calendario']);
        $project->articles()->attach($linkedArticle->id);
        $this->article(['title' => 'Un altro titolo qualsiasi']);
        $this->calendarDocument($project, "28/08/2026 — Un altro titolo qualsiasi\n");

        $report = $this->service()->reconcile($project);

        $this->assertCount(1, $report->articlesOutsidePlan);
        $this->assertSame($linkedArticle->id, $report->articlesOutsidePlan[0]->id);
    }

    public function test_a_linked_article_involved_in_an_ambiguous_match_is_still_considered_outside_the_plan(): void
    {
        $project = $this->project();
        $linkedArticle = $this->article(['title' => 'Titolo duplicato collegato']);
        $project->articles()->attach($linkedArticle->id);
        $this->article(['title' => 'Titolo duplicato collegato']);
        $this->calendarDocument($project, "28/08/2026 — Titolo duplicato collegato\n");

        $report = $this->service()->reconcile($project);

        $this->assertSame(EditorialCalendarMatchingService::MATCH_AMBIGUOUS, $report->entries[0]->match->matchType);
        $this->assertCount(1, $report->articlesOutsidePlan);
        $this->assertSame($linkedArticle->id, $report->articlesOutsidePlan[0]->id);
    }

    // ── Errori di parsing ─────────────────────────────────────────────

    public function test_unparseable_lines_are_surfaced_as_parse_errors_not_silently_dropped(): void
    {
        $project = $this->project();
        $this->calendarDocument(
            $project,
            "32/13/2026 — Data non valida\n28/08/2026 — Titolo valido\n"
        );

        $report = $this->service()->reconcile($project);

        $this->assertCount(1, $report->parseErrors);
        $this->assertCount(1, $report->entries);
    }

    // ── safeToAutoLink ────────────────────────────────────────────────

    public function test_an_unlinked_exact_match_is_safe_to_auto_link(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Titolo pronto per il collegamento automatico']);
        $this->calendarDocument($project, "28/08/2026 — Titolo pronto per il collegamento automatico\n");

        $report = $this->service()->reconcile($project);

        $this->assertCount(1, $report->safeToAutoLink());
    }

    public function test_an_already_linked_exact_match_is_not_in_the_safe_to_auto_link_list(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo già collegato']);
        $project->articles()->attach($article->id);
        $this->calendarDocument($project, "28/08/2026 — Titolo già collegato\n");

        $report = $this->service()->reconcile($project);

        $this->assertCount(0, $report->safeToAutoLink());
    }
}
