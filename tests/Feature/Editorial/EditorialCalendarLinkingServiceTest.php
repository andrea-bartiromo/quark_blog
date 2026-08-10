<?php

namespace Tests\Feature\Editorial;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use App\Models\User;
use App\Services\Editorial\EditorialCalendarLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialCalendarLinkingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EditorialCalendarLinkingService
    {
        return app(EditorialCalendarLinkingService::class);
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

    public function test_preview_never_writes_anything(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo da collegare']);
        $this->calendarDocument($project, "28/08/2026 — Titolo da collegare\n");

        $result = $this->service()->preview($project);

        $this->assertTrue($result->dryRun);
        $this->assertSame(0, $result->linkedCount());
        $this->assertCount(1, $result->report->safeToAutoLink());
        $this->assertCount(0, $project->articles()->where('articles.id', $article->id)->get());
    }

    public function test_apply_links_only_safe_matches(): void
    {
        $project = $this->project();
        $safeArticle = $this->article(['title' => 'Titolo esatto']);
        $this->article(['title' => 'Titolo duplicato']);
        $this->article(['title' => 'Titolo duplicato']);
        $this->calendarDocument(
            $project,
            "28/08/2026 — Titolo esatto\n29/08/2026 — Titolo duplicato\n30/08/2026 — Nessun articolo per questo\n"
        );

        $result = $this->service()->apply($project);

        $this->assertFalse($result->dryRun);
        $this->assertSame(1, $result->linkedCount());
        $this->assertSame($safeArticle->id, $result->linked[0]->article->id);
        $this->assertTrue($project->articles()->where('articles.id', $safeArticle->id)->exists());
    }

    public function test_apply_records_a_cronologia_entry_with_the_editorial_sync_source(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo tracciato in cronologia']);
        $this->calendarDocument($project, "28/08/2026 — Titolo tracciato in cronologia\n");

        $this->service()->apply($project);

        $log = ProjectActivityLog::where('project_id', $project->id)
            ->where('subject_type', 'project_article')
            ->where('subject_id', $article->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame(ProjectActivityLog::SOURCE_EDITORIAL_SYNC, $log->source);
        $this->assertNull($log->user_id);
        $this->assertStringContainsString('Titolo tracciato in cronologia', $log->action);
    }

    public function test_apply_is_idempotent_across_repeated_runs(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo idempotente']);
        $this->calendarDocument($project, "28/08/2026 — Titolo idempotente\n");

        $first = $this->service()->apply($project);
        $second = $this->service()->apply($project);

        $this->assertSame(1, $first->linkedCount());
        $this->assertSame(0, $second->linkedCount());
        $this->assertSame(1, $project->articles()->where('articles.id', $article->id)->count());
        $this->assertSame(
            1,
            ProjectActivityLog::where('project_id', $project->id)->where('subject_id', $article->id)->count()
        );
    }

    public function test_apply_never_detaches_a_manually_linked_article_that_is_now_outside_the_plan(): void
    {
        $project = $this->project();
        $manuallyLinked = $this->article(['title' => 'Articolo collegato manualmente, fuori piano']);
        $project->articles()->attach($manuallyLinked->id);
        $this->calendarDocument($project, "28/08/2026 — Un titolo completamente diverso\n");

        $this->service()->apply($project);

        $this->assertTrue($project->articles()->where('articles.id', $manuallyLinked->id)->exists());
    }

    public function test_apply_never_links_an_ambiguous_match(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Titolo ambiguo']);
        $this->article(['title' => 'Titolo ambiguo']);
        $this->calendarDocument($project, "28/08/2026 — Titolo ambiguo\n");

        $result = $this->service()->apply($project);

        $this->assertSame(0, $result->linkedCount());
        $this->assertSame(0, ProjectActivityLog::where('project_id', $project->id)->count());
    }

    public function test_a_project_without_a_calendar_document_links_nothing(): void
    {
        $project = $this->project();

        $result = $this->service()->apply($project);

        $this->assertSame(0, $result->linkedCount());
        $this->assertNull($result->report->documentId);
    }
}
