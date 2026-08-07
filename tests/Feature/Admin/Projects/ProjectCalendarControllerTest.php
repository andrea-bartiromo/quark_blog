<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_author_collaborator_cannot_access_the_calendar(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.calendar'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_calendar_shows_a_task_due_on_a_given_day_within_the_requested_month(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Pubblicazione articolo speciale',
            'due_date' => '2026-08-14',
        ]);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']))
            ->assertOk()
            ->assertSeeText('Pubblicazione articolo speciale');
    }

    public function test_calendar_does_not_show_a_task_due_in_a_different_month(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Task di settembre',
            'due_date' => '2026-09-05',
        ]);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']))
            ->assertOk()
            ->assertDontSeeText('Task di settembre');
    }

    /**
     * Regressione nota dalla prima implementazione: Carbon::translatedFormat()
     * usa i token di date() di PHP, non i pattern CLDR — 'MMMM' non è "mese
     * esteso", sono quattro 'M' distinti espansi e concatenati
     * ("agostoagostoagosto"). Il token corretto è 'F'.
     */
    public function test_calendar_title_shows_the_month_name_exactly_once(): void
    {
        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']));

        $response->assertSeeText('Calendario — agosto 2026');
        $response->assertDontSeeText('agostoagosto');
    }

    public function test_calendar_navigation_links_point_to_adjacent_months(): void
    {
        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']));

        $response->assertOk();
        $response->assertSee(route('admin.progettazione.calendar', ['month' => '2026-07']), false);
        $response->assertSee(route('admin.progettazione.calendar', ['month' => '2026-09']), false);
    }

    public function test_calendar_defaults_to_current_month_when_none_given(): void
    {
        Carbon::setTestNow('2026-08-06 10:00:00');

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.calendar'));

        $response->assertOk()->assertSeeText(Carbon::now()->translatedFormat('F Y'));

        Carbon::setTestNow();
    }

    /**
     * Rifinitura UX: il calendario deve offrire un collegamento evidente
     * alla creazione di un'attività, non solo la consultazione.
     */
    public function test_calendar_shows_a_new_task_cta(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.calendar'));

        $response->assertSeeText('Nuova attività');
        $response->assertSee(route('admin.progettazione.tasks.create-pick-project'), false);
    }

    // ── Blocco F: articoli programmati/pubblicati collegati a un progetto ──

    public function test_calendar_shows_a_scheduled_article_linked_to_a_project_on_its_editorial_date(): void
    {
        $project = Project::factory()->create();
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo in calendario',
            'slug' => 'articolo-in-calendario',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-14 09:00:00',
        ]);
        $project->articles()->attach($article->id);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']))
            ->assertOk()
            ->assertSeeText('Articolo in calendario');
    }

    public function test_calendar_does_not_show_an_unlinked_scheduled_article(): void
    {
        Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo non collegato',
            'slug' => 'articolo-non-collegato',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-14 09:00:00',
        ]);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']))
            ->assertOk()
            ->assertDontSeeText('Articolo non collegato');
    }

    public function test_calendar_does_not_show_a_draft_article_even_if_linked(): void
    {
        $project = Project::factory()->create();
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Bozza collegata',
            'slug' => 'bozza-collegata',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_DRAFT,
        ]);
        $project->articles()->attach($article->id);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']))
            ->assertOk()
            ->assertDontSeeText('Bozza collegata');
    }
}
