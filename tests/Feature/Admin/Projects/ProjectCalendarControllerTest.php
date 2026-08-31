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

    protected function tearDown(): void
    {
        try {
            Carbon::setTestNow();
        } finally {
            parent::tearDown();
        }
    }

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
        Carbon::setTestNow(Carbon::create(2026, 8, 6, 10, 0, 0, 'Europe/Rome'));

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.calendar'));

        $response->assertOk()->assertSeeText(Carbon::now()->translatedFormat('F Y'));
    }

    public function test_requested_month_does_not_inherit_the_current_day_at_a_shorter_month_boundary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 31, 10, 0, 0, 'Europe/Rome'));
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Attività del primo settembre',
            'due_date' => '2026-09-01',
        ]);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-09']))
            ->assertOk()
            ->assertSeeText('Calendario — settembre 2026')
            ->assertSeeText('Attività del primo settembre');
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

    /**
     * Regressione CodeRabbit/Codex: published_at è salvato in UTC. Un
     * articolo delle 22:30 UTC del 31/08 è in realtà delle 00:30 del 01/09
     * ora di Roma (CEST, +2) — deve comparire nel calendario di settembre
     * (giorno editoriale reale), non in quello di agosto.
     */
    public function test_calendar_places_an_article_on_its_rome_local_date_across_a_month_boundary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 31, 10, 0, 0, 'Europe/Rome'));
        $project = Project::factory()->create();
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo a cavallo di mezzanotte',
            'slug' => 'articolo-a-cavallo-di-mezzanotte',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-31 22:30:00',
        ]);
        $project->articles()->attach($article->id);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-09']))
            ->assertOk()
            ->assertSeeText('Articolo a cavallo di mezzanotte');

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-08']))
            ->assertOk()
            ->assertDontSeeText('Articolo a cavallo di mezzanotte');
    }

    public function test_calendar_places_a_cet_article_on_its_rome_local_date_across_the_year_boundary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 31, 10, 0, 0, 'Europe/Rome'));
        $project = Project::factory()->create();
        $article = Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo nel nuovo anno editoriale',
            'slug' => 'articolo-nel-nuovo-anno-editoriale',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_SCHEDULED,
            // 23:30 UTC del 31/12 = 00:30 CET del 01/01 a Roma.
            'published_at' => '2026-12-31 23:30:00',
        ]);
        $project->articles()->attach($article->id);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2027-01']))
            ->assertOk()
            ->assertSeeText('Articolo nel nuovo anno editoriale');

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-12']))
            ->assertOk()
            ->assertDontSeeText('Articolo nel nuovo anno editoriale');
    }

    public function test_calendar_preserves_the_rome_editorial_day_across_cet_and_cest_transitions(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 29, 12, 0, 0, 'Europe/Rome'));
        $project = Project::factory()->create();

        foreach ([
            ['Prima del salto CET CEST', 'prima-del-salto-cet-cest', '2026-03-28 23:30:00'],
            ['Dopo il salto CET CEST', 'dopo-il-salto-cet-cest', '2026-03-29 01:30:00'],
        ] as [$title, $slug, $publishedAt]) {
            $article = Article::create([
                'user_id' => User::factory()->create()->id,
                'title' => $title,
                'slug' => $slug,
                'body' => 'Corpo.',
                'category' => 'intelligenza-artificiale',
                'status' => Article::STATUS_SCHEDULED,
                'published_at' => $publishedAt,
            ]);
            $project->articles()->attach($article->id);
        }

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-03']))
            ->assertOk()
            ->assertSeeText('Prima del salto CET CEST')
            ->assertSeeText('Dopo il salto CET CEST');

        Carbon::setTestNow(Carbon::create(2026, 10, 25, 12, 0, 0, 'Europe/Rome'));
        foreach ([
            ['Prima del ritorno CEST CET', 'prima-del-ritorno-cest-cet', '2026-10-25 00:30:00'],
            ['Dopo il ritorno CEST CET', 'dopo-il-ritorno-cest-cet', '2026-10-25 01:30:00'],
        ] as [$title, $slug, $publishedAt]) {
            $article = Article::create([
                'user_id' => User::factory()->create()->id,
                'title' => $title,
                'slug' => $slug,
                'body' => 'Corpo.',
                'category' => 'intelligenza-artificiale',
                'status' => Article::STATUS_SCHEDULED,
                'published_at' => $publishedAt,
            ]);
            $project->articles()->attach($article->id);
        }

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertSeeText('Prima del ritorno CEST CET')
            ->assertSeeText('Dopo il ritorno CEST CET');
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
