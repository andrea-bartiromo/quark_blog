<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EditorialCalendarAuditCommandTest extends TestCase
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

    public function test_the_command_never_writes_anything(): void
    {
        $project = $this->project();
        $article = $this->article(['title' => 'Titolo da non toccare']);
        $this->calendarDocument($project, "28/08/2026 — Titolo da non toccare\n");

        $this->artisan('project:editorial-audit')->assertSuccessful();

        $this->assertFalse($project->articles()->where('articles.id', $article->id)->exists());
        $this->assertSame(0, ProjectActivityLog::where('project_id', $project->id)->count());
    }

    public function test_no_calendar_projects_at_all_still_succeeds(): void
    {
        $this->artisan('project:editorial-audit')->assertSuccessful();
    }

    public function test_json_output_reflects_the_reconciliation_state(): void
    {
        $project = $this->project();
        $this->article(['title' => 'Titolo con match esatto']);
        $this->calendarDocument(
            $project,
            "28/08/2026 — Titolo con match esatto\n29/08/2026 — Titolo senza articolo\n"
        );

        Artisan::call('project:editorial-audit', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertCount(1, $decoded);
        $this->assertSame($project->id, $decoded[0]['project_id']);
        $this->assertSame(2, $decoded[0]['total_planned']);
        $this->assertSame(1, $decoded[0]['missing_article_count']);
    }

    public function test_project_option_scopes_to_a_single_project(): void
    {
        $projectA = $this->project();
        $this->calendarDocument($projectA, "28/08/2026 — Voce A\n");

        $projectB = $this->project();
        $this->calendarDocument($projectB, "28/08/2026 — Voce B\n29/08/2026 — Voce C\n");

        Artisan::call('project:editorial-audit', ['--project' => $projectA->id, '--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertCount(1, $decoded);
        $this->assertSame(1, $decoded[0]['total_planned']);
    }

    public function test_an_unknown_project_id_fails_with_a_clear_error(): void
    {
        $this->artisan('project:editorial-audit', ['--project' => 999999])->assertFailed();
    }

    public function test_a_project_without_a_calendar_document_fails_when_targeted_explicitly(): void
    {
        $project = $this->project();

        $this->artisan('project:editorial-audit', ['--project' => $project->id])->assertFailed();
    }
}
