<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncEditorialCalendarCommandTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_default_run_is_dry_run_and_writes_nothing(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo da collegare via comando']);
        $this->calendarDocument($project, "28/08/2026 — Titolo da collegare via comando\n");

        $this->artisan('project:sync-editorial-calendar')
            ->assertSuccessful();

        $this->assertFalse($project->articles()->where('articles.id', $article->id)->exists());
    }

    public function test_execute_flag_applies_safe_links(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo collegato dal comando']);
        $this->calendarDocument($project, "28/08/2026 — Titolo collegato dal comando\n");

        $this->artisan('project:sync-editorial-calendar', ['--execute' => true])
            ->assertSuccessful();

        $this->assertTrue($project->articles()->where('articles.id', $article->id)->exists());
        $this->assertSame(
            1,
            ProjectActivityLog::where('project_id', $project->id)->where('source', ProjectActivityLog::SOURCE_EDITORIAL_SYNC)->count()
        );
    }

    public function test_project_option_scopes_to_a_single_project(): void
    {
        $projectA = $this->project();
        $articleA = $this->article(['title' => 'Titolo del progetto A']);
        $this->calendarDocument($projectA, "28/08/2026 — Titolo del progetto A\n");

        $projectB = $this->project();
        $articleB = $this->article(['title' => 'Titolo del progetto B']);
        $this->calendarDocument($projectB, "28/08/2026 — Titolo del progetto B\n");

        $this->artisan('project:sync-editorial-calendar', ['--project' => $projectA->id, '--execute' => true])
            ->assertSuccessful();

        $this->assertTrue($projectA->articles()->where('articles.id', $articleA->id)->exists());
        $this->assertFalse($projectB->articles()->where('articles.id', $articleB->id)->exists());
    }

    public function test_an_unknown_project_id_fails_with_a_clear_error(): void
    {
        $this->artisan('project:sync-editorial-calendar', ['--project' => 999999])
            ->assertFailed();
    }

    public function test_a_project_without_a_calendar_document_fails_when_targeted_explicitly(): void
    {
        $project = $this->project();

        $this->artisan('project:sync-editorial-calendar', ['--project' => $project->id])
            ->assertFailed();
    }

    public function test_no_calendar_projects_at_all_still_succeeds(): void
    {
        $this->artisan('project:sync-editorial-calendar')
            ->assertSuccessful();
    }

    public function test_running_twice_with_execute_is_idempotent(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo idempotente da comando']);
        $this->calendarDocument($project, "28/08/2026 — Titolo idempotente da comando\n");

        $this->artisan('project:sync-editorial-calendar', ['--execute' => true])->assertSuccessful();
        $this->artisan('project:sync-editorial-calendar', ['--execute' => true])->assertSuccessful();

        $this->assertSame(1, $project->articles()->where('articles.id', $article->id)->count());
        $this->assertSame(
            1,
            ProjectActivityLog::where('project_id', $project->id)->where('subject_id', $article->id)->count()
        );
    }
}
