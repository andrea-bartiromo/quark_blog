<?php

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SearchConsoleQuery;
use App\Models\SearchOpportunityDecision;
use App\Models\User;
use App\Services\SearchConsole\SearchOpportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Correzione UX isolata: "Collegamento diretto tra Decisioni SEO e
 * Opportunità di ricerca". Da ogni riga di /admin/decisioni-editoriali-seo
 * (coda read-only calcolata da EditorialOpportunityDecisionService) si deve
 * poter raggiungere con un'azione esplicita la riga corrispondente su
 * /admin/search-opportunities (stessa opportunity_key, identità stabile
 * type|query|page_url — App\Services\SearchConsole\SearchOpportunity::$key)
 * e, se esiste già, il brief/task collegato dalla decisione umana
 * (App\Models\SearchOpportunityDecision, fonte di verità persistente, mai
 * duplicata qui). Nessuna scrittura: entrambe le pagine restano GET/read-only.
 */
class EditorialOpportunityDecisionOpportunityLinkTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    /**
     * Stesso fixture già usato altrove in questo file di test
     * (EditorialOpportunityDecisionControllerTest): query senza pagina,
     * impressions/ctr/position tali da cadere nel ramo
     * high_impression_low_ctr di SearchOpportunityScoringService::forPeriod().
     *
     * @return string opportunity_key reale (type|query|)
     */
    private function seedOpportunity(string $query = 'query di collegamento'): string
    {
        SearchConsoleQuery::create([
            'query' => $query, 'page_url' => '', 'article_id' => null,
            'clicks' => 0, 'impressions' => 100, 'ctr' => 0.0, 'position' => 15.0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-07', 'import_batch' => 'fixture', 'imported_at' => now(),
        ]);

        return 'high_impression_low_ctr|'.$query.'|';
    }

    private function decisioniSeoUrl(): string
    {
        return route('admin.editorial-opportunity-decisions', ['period' => '2026-09-01|2026-09-07']);
    }

    public function test_the_link_points_to_the_exact_row_shown_on_search_opportunities(): void
    {
        $key = $this->seedOpportunity('query di collegamento diretto');
        $anchor = SearchOpportunity::anchorId($key);

        $decisioniResponse = $this->actingAs($this->editor())->get($this->decisioniSeoUrl());
        $decisioniResponse->assertOk();
        $decisioniResponse->assertSee(
            route('admin.search-opportunities', ['tipo' => 'high_impression_low_ctr']).'#'.$anchor,
            false
        );

        // La stessa ancora deve davvero esistere come id di riga sull'altra
        // pagina — altrimenti il link "arriva" ma non porta da nessuna
        // parte.
        $opportunitiesResponse = $this->actingAs($this->editor())->get(route('admin.search-opportunities'));
        $opportunitiesResponse->assertOk();
        $opportunitiesResponse->assertSee('id="'.$anchor.'"', false);
    }

    public function test_it_links_to_the_existing_task_when_a_decision_with_a_project_task_already_exists(): void
    {
        $key = $this->seedOpportunity('query con brief già creato');

        $project = Project::create([
            'title' => 'Editoriale', 'slug' => 'editoriale-link-test', 'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IN_PROGRESS, 'is_default_editorial' => true,
        ]);
        $editor = $this->editor();
        $task = ProjectTask::create([
            'project_id' => $project->id, 'title' => 'Brief: query con brief già creato',
            'type' => ProjectTask::TYPE_PUBLICATION, 'manual_status' => ProjectTask::STATUS_TODO,
            'created_by' => $editor->id,
        ]);
        SearchOpportunityDecision::create([
            'opportunity_key' => $key, 'opportunity_key_hash' => hash('sha256', $key),
            'opportunity_type' => 'high_impression_low_ctr', 'opportunity_query' => 'query con brief già creato',
            'decision_type' => SearchOpportunityDecision::DECISION_CREATE_BRIEF,
            'project_task_id' => $task->id, 'created_by' => $editor->id, 'updated_by' => $editor->id,
        ]);

        $response = $this->actingAs($editor)->get($this->decisioniSeoUrl());

        $response->assertOk();
        $response->assertSee('Apri brief/task');
        $response->assertSee(route('admin.progettazione.projects.tasks.edit', [$project->id, $task->id]), false);
    }

    public function test_it_omits_the_task_link_when_no_decision_is_recorded_yet(): void
    {
        $this->seedOpportunity('query senza decisione registrata');

        $response = $this->actingAs($this->editor())->get($this->decisioniSeoUrl());

        $response->assertOk();
        $response->assertSee('Apri in Opportunità di ricerca');
        $response->assertDontSee('Apri brief/task');
        $this->assertDatabaseCount('search_opportunity_decisions', 0);
    }

    public function test_an_author_cannot_view_the_decisions_dashboard(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $this->seedOpportunity('query non raggiungibile da author');

        $this->actingAs($author)->get($this->decisioniSeoUrl())->assertRedirect(route('redazione.dashboard'));
    }

    public function test_opening_either_page_never_writes_a_decision(): void
    {
        $key = $this->seedOpportunity('query senza side effect');

        $editor = $this->editor();
        $this->actingAs($editor)->get($this->decisioniSeoUrl())->assertOk();
        $this->actingAs($editor)->get(route('admin.search-opportunities', ['tipo' => 'high_impression_low_ctr']).'#'.SearchOpportunity::anchorId($key))->assertOk();

        $this->assertDatabaseCount('search_opportunity_decisions', 0);
        $this->assertDatabaseCount('project_tasks', 0);
    }

    /**
     * decisionsFor() (SearchOpportunityDecisionService, già usato da
     * SearchOpportunityController) resta O(1) in query indipendentemente
     * dal numero di righe — stesso principio già verificato altrove in
     * questo codebase (es. ContentGraphPublicSafetyContractTest). Qui si
     * verifica che riusarlo da EditorialOpportunityDecisionController non
     * introduca una query per riga.
     */
    public function test_looking_up_existing_decisions_does_not_grow_its_query_count_with_the_number_of_rows(): void
    {
        // Confronto DELTA tra poche righe e molte righe, mai una soglia
        // assoluta: la pagina esegue già diverse query indipendenti da
        // questo collegamento (readiness, profili, sessione...), quindi un
        // singolo numero assoluto non distinguerebbe in modo affidabile un
        // vero O(1) da un N+1 con N piccolo. Stesso principio già in uso in
        // ContentGraphPublicSafetyContractTest.
        $editor = $this->editor();

        foreach (range(1, 2) as $i) {
            $this->seedOpportunity("query budget piccola $i");
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($editor)->get($this->decisioniSeoUrl())->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        foreach (range(3, 12) as $i) {
            $this->seedOpportunity("query budget grande $i");
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($editor)->get($this->decisioniSeoUrl());
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $response->assertSee('query budget grande 12');
        $this->assertLessThanOrEqual(
            $smallCount + 2,
            $largeCount,
            'Il collegamento a SearchOpportunityDecision non deve crescere linearmente con il numero di righe (niente query-per-riga — decisionsFor() resta bulk).'
        );
    }
}
