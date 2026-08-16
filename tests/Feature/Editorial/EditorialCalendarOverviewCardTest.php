<?php

namespace Tests\Feature\Editorial;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialCalendarOverviewCardTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_the_overview_tab_shows_no_editorial_card_when_the_project_has_no_calendar(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'overview']));

        $response->assertOk();
        $response->assertDontSeeText('Piano editoriale');
    }

    public function test_the_overview_tab_shows_the_compact_editorial_card_when_a_calendar_is_marked(): void
    {
        $project = Project::factory()->create();

        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo pubblicato in piano',
            'slug' => 'articolo-pubblicato-in-piano-'.uniqid(),
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => '2026-08-28 09:00:00',
        ]);

        ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'content' => "28/08/2026 — Articolo pubblicato in piano\n29/08/2026 — Articolo mai scritto\n",
            'is_editorial_calendar' => true,
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'overview']));

        $response->assertOk();
        $response->assertSeeText('Piano editoriale');
        $response->assertViewHas('editorialProgress', function ($progress) {
            return $progress->totalPlanned === 2
                && $progress->publishedCount === 1
                && $progress->missingArticleCount === 1;
        });
    }

    public function test_other_tabs_never_compute_the_editorial_card_even_with_a_calendar_marked(): void
    {
        $project = Project::factory()->create();
        ProjectDocument::factory()->create([
            'project_id' => $project->id,
            'content' => "28/08/2026 — Voce qualsiasi\n",
            'is_editorial_calendar' => true,
        ]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'tasks']));

        $response->assertOk();
        $response->assertViewHas('editorialProgress', null);
    }
}
