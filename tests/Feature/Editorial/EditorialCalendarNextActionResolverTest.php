<?php

namespace Tests\Feature\Editorial;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Editorial\EditorialCalendarNextActionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialCalendarNextActionResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): EditorialCalendarNextActionResolver
    {
        return app(EditorialCalendarNextActionResolver::class);
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

    public function test_a_project_without_a_calendar_document_falls_back_to_the_task_based_suggestion(): void
    {
        $project = $this->project();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Attività da avviare',
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame($project->suggestedNextAction(), $suggestion);
    }

    public function test_a_missing_article_produces_a_concrete_suggestion_naming_the_title_and_date(): void
    {
        $project = $this->project();
        $this->calendarDocument($project, "28/08/2026 — Titolo mai scritto\n");

        $suggestion = $this->resolver()->resolve($project);

        $this->assertStringContainsString('Titolo mai scritto', $suggestion);
        $this->assertStringContainsString('28/08/2026', $suggestion);
    }

    public function test_the_earliest_missing_entry_is_suggested_first(): void
    {
        $project = $this->project();
        $this->calendarDocument(
            $project,
            "30/08/2026 — Titolo più lontano\n29/08/2026 — Titolo più vicino\n"
        );

        $suggestion = $this->resolver()->resolve($project);

        $this->assertStringContainsString('Titolo più vicino', $suggestion);
        $this->assertStringNotContainsString('Titolo più lontano', $suggestion);
    }

    public function test_an_ambiguous_match_takes_priority_over_a_missing_article(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Titolo duplicato']);
        $this->article(['title' => 'Titolo duplicato']);
        $this->calendarDocument(
            $project,
            "28/08/2026 — Titolo duplicato\n29/08/2026 — Un titolo senza alcun articolo\n"
        );

        $suggestion = $this->resolver()->resolve($project);

        $this->assertStringContainsString('Verificare manualmente', $suggestion);
        $this->assertStringContainsString('Titolo duplicato', $suggestion);
    }

    public function test_a_date_discrepancy_is_surfaced_when_nothing_more_urgent_exists(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo pubblicato in anticipo',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => '2026-08-20 10:00:00',
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo pubblicato in anticipo\n");

        $suggestion = $this->resolver()->resolve($project);

        $this->assertStringContainsString('Titolo pubblicato in anticipo', $suggestion);
        $this->assertStringContainsString('anticipo', $suggestion);
    }

    public function test_a_status_mismatch_is_surfaced_when_nothing_more_urgent_exists(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo con stato in conflitto',
            'status' => Article::STATUS_DRAFT,
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo con stato in conflitto [pubblicato]\n");

        $suggestion = $this->resolver()->resolve($project);

        $this->assertStringContainsString('Titolo con stato in conflitto', $suggestion);
        $this->assertStringContainsString('Bozza', $suggestion);
    }

    public function test_a_fully_reconciled_calendar_falls_back_to_the_task_based_suggestion(): void
    {
        $project = $this->project();
        $this->article([
            'title' => 'Titolo perfettamente allineato',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-28 09:00:00',
        ]);
        $this->calendarDocument($project, "28/08/2026 — Titolo perfettamente allineato\n");

        ProjectTask::factory()->for($project)->create([
            'title' => 'Task residua',
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $suggestion = $this->resolver()->resolve($project);

        $this->assertSame($project->suggestedNextAction(), $suggestion);
        $this->assertStringContainsString('Task residua', $suggestion);
    }
}
