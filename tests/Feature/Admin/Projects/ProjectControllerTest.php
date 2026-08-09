<?php

namespace Tests\Feature\Admin\Projects;

use App\Models\Project;
use App\Models\ProjectActivityLog;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_author_collaborator_cannot_access_the_projects_index(): void
    {
        $author = User::factory()->create(['role' => 'author']);

        $this->actingAs($author)
            ->get(route('admin.progettazione.projects.index'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.progettazione.projects.index'))
            ->assertRedirect(route('login'));
    }

    public function test_editor_can_see_the_projects_index(): void
    {
        Project::factory()->create(['title' => 'Speciale Enigma']);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.index'))
            ->assertOk()
            ->assertSeeText('Speciale Enigma');
    }

    public function test_editor_can_create_a_project(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.progettazione.projects.store'), [
            'title' => 'Speciale Enigma',
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_HIGH,
        ]);

        $project = Project::firstWhere('title', 'Speciale Enigma');
        $response->assertRedirect(route('admin.progettazione.projects.show', $project));
        $this->assertNotNull($project);
        $this->assertSame('speciale-enigma', $project->slug);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Progetto creato',
        ]);
    }

    public function test_creating_a_project_with_a_duplicate_title_does_not_error_and_gets_a_distinct_slug(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.progettazione.projects.store'), [
            'title' => 'Speciale Enigma',
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_HIGH,
        ])->assertRedirect();

        $second = $this->actingAs($editor)->post(route('admin.progettazione.projects.store'), [
            'title' => 'Speciale Enigma',
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_HIGH,
        ]);

        $second->assertSessionHasNoErrors();
        $second->assertStatus(302);

        $project = Project::firstWhere('slug', 'speciale-enigma-2');
        $this->assertNotNull($project);
    }

    public function test_editor_can_view_a_project_overview(): void
    {
        $project = Project::factory()->create(['title' => 'Speciale Enigma', 'description' => 'Descrizione di prova']);

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', $project))
            ->assertOk()
            ->assertSeeText('Speciale Enigma')
            ->assertSeeText('Descrizione di prova');
    }

    public function test_editor_can_update_a_project(): void
    {
        $project = Project::factory()->create(['title' => 'Titolo originale']);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => 'Titolo aggiornato',
            'type' => $project->type,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
        ])->assertRedirect(route('admin.progettazione.projects.show', $project));

        $this->assertSame('Titolo aggiornato', $project->fresh()->title);
        $this->assertSame('titolo-originale', $project->fresh()->slug);
    }

    // ── Audit 0, gap #1: Cronologia su modifica titolo/priorità ──────

    public function test_updating_only_the_title_is_recorded_in_the_activity_log(): void
    {
        $project = Project::factory()->create(['title' => 'Titolo originale']);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => 'Titolo aggiornato',
            'type' => $project->type,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
        ]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Titolo progetto cambiato da «Titolo originale» a «Titolo aggiornato»',
            'old_value' => 'Titolo originale',
            'new_value' => 'Titolo aggiornato',
        ]);
    }

    public function test_updating_only_the_priority_is_recorded_in_the_activity_log(): void
    {
        $project = Project::factory()->create(['priority' => Project::PRIORITY_MEDIUM]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => $project->title,
            'type' => $project->type,
            'operational_status' => $project->operational_status,
            'priority' => Project::PRIORITY_CRITICAL,
        ]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Priorità progetto cambiata da «Media» a «Critica»',
            'old_value' => Project::PRIORITY_MEDIUM,
            'new_value' => Project::PRIORITY_CRITICAL,
        ]);
    }

    public function test_updating_a_project_without_changing_title_or_priority_creates_no_spurious_log_entries(): void
    {
        $project = Project::factory()->create([
            'title' => 'Titolo invariato',
            'priority' => Project::PRIORITY_MEDIUM,
            'operational_status' => Project::STATUS_IDEA,
        ]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => 'Titolo invariato',
            'type' => $project->type,
            'operational_status' => Project::STATUS_IDEA,
            'priority' => Project::PRIORITY_MEDIUM,
        ]);

        $this->assertSame(0, ProjectActivityLog::where('project_id', $project->id)->count());
    }

    public function test_updating_only_operational_status_via_the_full_form_keeps_the_existing_log_message_format(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_IDEA]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => $project->title,
            'type' => $project->type,
            'operational_status' => Project::STATUS_IN_PROGRESS,
            'priority' => $project->priority,
        ]);

        $this->assertSame(1, ProjectActivityLog::where('project_id', $project->id)->count());
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Stato progetto cambiato da «Idea» a «In lavorazione»',
        ]);
    }

    public function test_changing_title_priority_and_status_together_creates_three_separate_log_entries(): void
    {
        $project = Project::factory()->create([
            'title' => 'Titolo originale',
            'priority' => Project::PRIORITY_MEDIUM,
            'operational_status' => Project::STATUS_IDEA,
        ]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => 'Titolo aggiornato',
            'type' => $project->type,
            'operational_status' => Project::STATUS_IN_PROGRESS,
            'priority' => Project::PRIORITY_CRITICAL,
        ]);

        $this->assertSame(3, ProjectActivityLog::where('project_id', $project->id)->count());
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Titolo progetto cambiato da «Titolo originale» a «Titolo aggiornato»',
        ]);
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Priorità progetto cambiata da «Media» a «Critica»',
        ]);
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Stato progetto cambiato da «Idea» a «In lavorazione»',
        ]);
    }

    public function test_quick_status_change_updates_status_without_the_full_form(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_IDEA]);

        $this->actingAs($this->editor())
            ->patch(route('admin.progettazione.projects.update-status', $project), [
                'operational_status' => Project::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect(route('admin.progettazione.projects.show', $project));

        $this->assertSame(Project::STATUS_IN_PROGRESS, $project->fresh()->operational_status);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'action' => 'Stato progetto cambiato da «Idea» a «In lavorazione»',
        ]);
    }

    public function test_quick_status_change_to_the_same_status_does_not_create_a_duplicate_log_entry(): void
    {
        $project = Project::factory()->create(['operational_status' => Project::STATUS_IDEA]);

        $this->actingAs($this->editor())->patch(route('admin.progettazione.projects.update-status', $project), [
            'operational_status' => Project::STATUS_IDEA,
        ]);

        $this->assertSame(0, ProjectActivityLog::where('project_id', $project->id)->count());
    }

    public function test_editor_can_delete_a_project(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->editor())
            ->delete(route('admin.progettazione.projects.destroy', $project))
            ->assertRedirect(route('admin.progettazione.projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_index_can_be_filtered_by_status(): void
    {
        Project::factory()->create(['title' => 'Bloccato', 'operational_status' => Project::STATUS_BLOCKED]);
        Project::factory()->create(['title' => 'In corso', 'operational_status' => Project::STATUS_IN_PROGRESS]);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.index', ['status' => Project::STATUS_BLOCKED]));

        $response->assertSeeText('Bloccato');
        $response->assertDontSeeText('In corso');
    }

    public function test_index_orders_projects_by_priority_severity_not_alphabetically(): void
    {
        Project::factory()->create(['title' => 'Media', 'priority' => Project::PRIORITY_MEDIUM]);
        Project::factory()->create(['title' => 'Critica', 'priority' => Project::PRIORITY_CRITICAL]);
        Project::factory()->create(['title' => 'Bassa', 'priority' => Project::PRIORITY_LOW]);
        Project::factory()->create(['title' => 'Alta', 'priority' => Project::PRIORITY_HIGH]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.index'));

        // Isola il corpo della tabella: la sidebar contiene un link "Media"
        // che altrimenti falserebbe la ricerca testuale su tutta la pagina.
        $content = strstr($response->getContent(), 'admin-table');
        $posCritica = strpos($content, 'Critica');
        $posAlta = strpos($content, 'Alta');
        $posMedia = strpos($content, 'Media');
        $posBassa = strpos($content, 'Bassa');

        $this->assertTrue($posCritica < $posAlta && $posAlta < $posMedia && $posMedia < $posBassa);
    }

    public function test_history_tab_lists_activity_log_entries_newest_first(): void
    {
        $project = Project::factory()->create();
        $editor = $this->editor();

        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Progetto creato', $editor->id);
        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Stato progetto cambiato', $editor->id);

        $response = $this->actingAs($editor)
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Stato progetto cambiato') < strpos($content, 'Progetto creato'));
    }

    public function test_history_tab_shows_an_empty_state_when_no_activity_yet(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']))
            ->assertSeeText('Nessuna attività registrata ancora.');
    }

    /**
     * Correzione #5 (stessa di Documenti/Prompt/Decisioni): la Cronologia
     * è paginata (15 per pagina) invece di un elenco illimitato.
     */
    public function test_history_tab_paginates_at_fifteen_per_page(): void
    {
        $project = Project::factory()->create();
        $editor = $this->editor();

        for ($i = 0; $i < 16; $i++) {
            ProjectActivityLog::record($project, 'project', $project->id, $project->title, "Voce {$i}", $editor->id);
        }

        $response = $this->actingAs($editor)
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']));

        $response->assertOk();
        $response->assertViewHas('activityLog', fn ($paginator) => $paginator->count() === 15);
        $response->assertSee('page=2', false);
    }

    public function test_history_tab_returns_a_paginator_not_a_plain_collection(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']));

        $response->assertViewHas('activityLog', fn ($activityLog) => $activityLog instanceof \Illuminate\Pagination\LengthAwarePaginator);
    }

    public function test_history_tab_second_page_shows_the_remaining_entries_preserving_the_tab_query_param(): void
    {
        $project = Project::factory()->create();
        $editor = $this->editor();

        // created_at identico per tutte le voci (inserite nello stesso
        // istante): l'ordinamento secondario per id (già presente nel
        // controller) resta l'unico modo per avere un ordine deterministico
        // tra pagina 1 e pagina 2 — senza, il taglio a 15 sarebbe arbitrario.
        for ($i = 0; $i < 17; $i++) {
            ProjectActivityLog::record($project, 'project', $project->id, $project->title, "Voce {$i}", $editor->id);
        }

        $secondPage = $this->actingAs($editor)
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history', 'page' => 2]));

        $secondPage->assertOk();
        $secondPage->assertViewHas('activityLog', fn ($paginator) => $paginator->count() === 2);
        // withQueryString() deve preservare "tab" insieme a "page", altrimenti
        // il link "successiva" riporterebbe l'utente sulla tab "overview".
        $secondPage->assertSee('tab=history', false);
    }

    public function test_history_tab_order_is_unaffected_by_pagination(): void
    {
        $project = Project::factory()->create();
        $editor = $this->editor();

        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Prima voce', $editor->id);
        ProjectActivityLog::record($project, 'project', $project->id, $project->title, 'Seconda voce', $editor->id);

        $response = $this->actingAs($editor)
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'history']));

        $content = $response->getContent();
        $this->assertTrue(strpos($content, 'Seconda voce') < strpos($content, 'Prima voce'));
    }

    /**
     * Rifinitura UX: "Elimina progetto" deve essere chiaramente visibile
     * nella pagina dettaglio, non solo raggiungibile via API/rotta.
     */
    public function test_delete_project_action_is_visible_on_the_project_detail_page(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.show', $project));

        $response->assertSeeText('Elimina progetto');
        $response->assertSee('action="'.route('admin.progettazione.projects.destroy', $project).'"', false);
    }

    public function test_delete_project_action_is_visible_on_the_edit_form(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.edit', $project));

        $response->assertSeeText('Elimina progetto');
    }

    /**
     * Rifinitura UX: il cambio rapido di stato deve essere un widget
     * riconoscibile (classe dedicata), non una <select> isolata.
     */
    public function test_quick_status_switcher_widget_is_present_on_the_detail_page(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.show', $project));

        $response->assertSee('project-status-switcher', false);
        $response->assertSeeText('Stato progetto');
    }

    /**
     * Rifinitura UX: le schede del dettaglio progetto usano una classe CSS
     * dedicata (bar leggibile, scrollabile su mobile) invece di stili
     * inline a basso contrasto per i tab non attivi.
     */
    public function test_project_tabs_use_the_dedicated_readable_tab_bar_class(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.show', $project));

        $response->assertSee('class="project-tabs"', false);

        foreach (['Panoramica', 'Roadmap', 'Attività', 'Articoli', 'Documenti', 'Prompt', 'Decisioni', 'Cronologia'] as $tab) {
            $response->assertSeeText($tab);
        }
    }

    // ── Blocco E: avanzamento automatico e suggerimento prossima azione ──

    public function test_progress_field_is_read_only_in_the_form(): void
    {
        $project = Project::factory()->create(['progress' => 40]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.edit', $project));

        $response->assertOk();
        $response->assertDontSee('name="progress"', false);
        $response->assertSeeText('40%');
    }

    public function test_submitting_a_progress_value_from_the_form_is_ignored(): void
    {
        $project = Project::factory()->create(['progress' => 0]);
        ProjectTask::factory()->for($project)->create(['manual_status' => ProjectTask::STATUS_TODO]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => $project->title,
            'type' => $project->type,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
            'progress' => 99,
        ]);

        // 0 task completate su 1 = 0%, non il 99 inviato dal form.
        $this->assertSame(0, $project->fresh()->progress);
    }

    public function test_edit_form_shows_a_suggested_next_action_with_an_apply_button(): void
    {
        $project = Project::factory()->create();
        ProjectTask::factory()->for($project)->create([
            'title' => 'Attività da avviare',
            'manual_status' => ProjectTask::STATUS_TODO,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.edit', $project));

        $response->assertOk();
        $response->assertSeeText('Avviare l\'attività: «Attività da avviare»');
        $response->assertSee('Applica', false);
    }

    public function test_new_project_form_shows_no_suggestion(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.progettazione.projects.create'));

        $response->assertOk();
        $response->assertDontSee('💡 Suggerimento', false);
    }

    // ── Blocco F: roadmap alimentata dagli articoli collegati ────────

    public function test_roadmap_tab_shows_a_linked_scheduled_article_on_its_editorial_date(): void
    {
        $project = Project::factory()->create();
        $article = \App\Models\Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo in roadmap',
            'slug' => 'articolo-in-roadmap',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => \App\Models\Article::STATUS_SCHEDULED,
            'published_at' => now()->addDays(3),
        ]);
        $project->articles()->attach($article->id);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'roadmap']));

        $response->assertOk();
        $response->assertSeeText('Articolo in roadmap');
    }

    /**
     * Regressione CodeRabbit/Codex: published_at è salvato in UTC. Un
     * articolo delle 22:30 UTC del 31/08 è in realtà delle 00:30 del 01/09
     * ora di Roma (CEST, +2) — la roadmap deve mostrare la data editoriale
     * (Article::publishedAtForEditors()), non quella UTC grezza.
     */
    public function test_roadmap_tab_shows_the_editorial_rome_date_not_the_raw_utc_date(): void
    {
        $project = Project::factory()->create();
        $article = \App\Models\Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Articolo a cavallo di mezzanotte',
            'slug' => 'articolo-a-cavallo-di-mezzanotte',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => \App\Models\Article::STATUS_SCHEDULED,
            'published_at' => '2026-08-31 22:30:00',
        ]);
        $project->articles()->attach($article->id);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'roadmap']));

        $response->assertOk();
        $response->assertSeeText('01/09/2026');
        $response->assertDontSeeText('31/08/2026');
    }

    public function test_roadmap_tab_does_not_show_a_draft_linked_article(): void
    {
        $project = Project::factory()->create();
        $article = \App\Models\Article::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Bozza in roadmap',
            'slug' => 'bozza-in-roadmap',
            'body' => 'Corpo.',
            'category' => 'intelligenza-artificiale',
            'status' => \App\Models\Article::STATUS_DRAFT,
        ]);
        $project->articles()->attach($article->id);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.show', [$project, 'tab' => 'roadmap']));

        $response->assertOk();
        $response->assertDontSeeText('Bozza in roadmap');
    }

    // ── Hardening: esposizione UI di is_default_editorial ────────────

    public function test_editor_can_mark_a_project_as_the_default_editorial_project_via_the_form(): void
    {
        $project = Project::factory()->create(['type' => Project::TYPE_EDITORIAL_SPECIAL, 'is_default_editorial' => false]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => $project->title,
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
            'is_default_editorial' => '1',
        ]);

        $this->assertTrue($project->fresh()->is_default_editorial);
    }

    public function test_unchecking_the_default_editorial_box_clears_it(): void
    {
        $project = Project::factory()->create(['type' => Project::TYPE_EDITORIAL_SPECIAL, 'is_default_editorial' => true]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => $project->title,
            'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
            // is_default_editorial assente: checkbox non spuntata.
        ]);

        $this->assertFalse($project->fresh()->is_default_editorial);
    }

    public function test_setting_a_new_default_via_the_form_unsets_the_previous_one(): void
    {
        $first = Project::factory()->create(['type' => Project::TYPE_EDITORIAL_SPECIAL, 'is_default_editorial' => true]);
        $second = Project::factory()->create(['type' => Project::TYPE_ARTICLE_SERIES, 'is_default_editorial' => false]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $second), [
            'title' => $second->title,
            'type' => Project::TYPE_ARTICLE_SERIES,
            'operational_status' => $second->operational_status,
            'priority' => $second->priority,
            'is_default_editorial' => '1',
        ]);

        $this->assertFalse($first->fresh()->is_default_editorial);
        $this->assertTrue($second->fresh()->is_default_editorial);
    }

    public function test_a_technical_project_cannot_become_default_editorial_via_the_form(): void
    {
        $project = Project::factory()->create(['type' => Project::TYPE_TECHNICAL_IMPROVEMENT]);

        $this->actingAs($this->editor())->put(route('admin.progettazione.projects.update', $project), [
            'title' => $project->title,
            'type' => Project::TYPE_TECHNICAL_IMPROVEMENT,
            'operational_status' => $project->operational_status,
            'priority' => $project->priority,
            'is_default_editorial' => '1',
        ]);

        $this->assertFalse($project->fresh()->is_default_editorial);
    }

    public function test_default_editorial_checkbox_is_visible_only_for_eligible_types(): void
    {
        $editorialProject = Project::factory()->create(['type' => Project::TYPE_EDITORIAL_SPECIAL]);
        $technicalProject = Project::factory()->create(['type' => Project::TYPE_TECHNICAL_IMPROVEMENT]);

        $editorialResponse = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.edit', $editorialProject));
        $editorialResponse->assertSee('id="default-editorial-group" style="display: ;"', false);

        $technicalResponse = $this->actingAs($this->editor())
            ->get(route('admin.progettazione.projects.edit', $technicalProject));
        $technicalResponse->assertSee('id="default-editorial-group" style="display: none;"', false);
    }
}
