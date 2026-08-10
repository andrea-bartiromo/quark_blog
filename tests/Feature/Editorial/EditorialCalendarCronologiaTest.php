<?php

namespace Tests\Feature\Editorial;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialCalendarCronologiaTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_the_history_tab_labels_an_editorial_sync_entry_distinctly_from_manual_and_automatic(): void
    {
        $project = Project::factory()->create();
        $editor = $this->editor();

        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo sincronizzato',
            'slug' => 'articolo-sincronizzato-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ]);

        ProjectActivityLog::record(
            $project,
            'project_article',
            $article->id,
            $article->title,
            'Articolo collegato automaticamente dalla sincronizzazione del calendario editoriale (voce #1): «Articolo sincronizzato»',
            null,
            source: ProjectActivityLog::SOURCE_EDITORIAL_SYNC,
        );
        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Progetto creato', $editor->id);
        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Collegamento automatico standard', null, source: ProjectActivityLog::SOURCE_SYSTEM);

        $response = $this->actingAs($editor)
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']));

        $response->assertOk();
        $response->assertSeeText('Sync calendario');
        $response->assertSeeText('Automatico');
        $response->assertSeeText('Manuale');
    }
}
