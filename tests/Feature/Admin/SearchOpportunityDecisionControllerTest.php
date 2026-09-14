<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\SearchOpportunityDecision;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 4 (programma "Kairus Organic Discovery"): decisione editoriale
 * tracciabile per opportunità di ricerca. Usa le opportunità da ricerca
 * interna a zero risultati (SearchZeroResultQuery) come fonte
 * deterministica — non dipendono da alcun import CSV Search Console.
 */
class SearchOpportunityDecisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    /** L'opportunity_key reale per questa query interna a zero risultati. */
    private function seedZeroResultOpportunity(string $query = 'ricerca senza risultati', int $hits = 5): string
    {
        SearchZeroResultQuery::create(['normalized_query' => $query, 'hit_count' => $hits]);

        return 'internal_zero_result_search|'.$query.'|';
    }

    public function test_guest_cannot_record_a_decision(): void
    {
        $key = $this->seedZeroResultOpportunity();

        $this->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
            'rationale' => 'Motivo.',
        ])->assertRedirect(route('login'));
    }

    public function test_an_author_cannot_record_a_decision(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $key = $this->seedZeroResultOpportunity();

        $this->actingAs($author)->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
            'rationale' => 'Motivo.',
        ])->assertRedirect(route('redazione.dashboard'));

        $this->assertDatabaseCount('search_opportunity_decisions', 0);
    }

    public function test_editor_can_record_an_ignore_decision_with_rationale(): void
    {
        $key = $this->seedZeroResultOpportunity();

        $this->actingAs($this->editor())->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
            'rationale' => 'Query troppo generica, nessuna azione utile.',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('search_opportunity_decisions', [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
        ]);
    }

    public function test_ignore_without_a_rationale_is_rejected(): void
    {
        $key = $this->seedZeroResultOpportunity();

        $this->actingAs($this->editor())->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
        ])->assertSessionHasErrors('rationale');

        $this->assertDatabaseCount('search_opportunity_decisions', 0);
    }

    public function test_update_article_without_an_article_id_is_rejected(): void
    {
        $key = $this->seedZeroResultOpportunity();

        $this->actingAs($this->editor())->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE,
        ])->assertSessionHasErrors('article_id');

        $this->assertDatabaseCount('search_opportunity_decisions', 0);
    }

    public function test_update_article_with_a_valid_article_links_it(): void
    {
        $key = $this->seedZeroResultOpportunity();
        $article = Article::withoutEvents(fn () => Article::create([
            'user_id' => $this->editor()->id,
            'title' => 'Articolo esistente', 'slug' => 'articolo-esistente-decisione',
            'excerpt' => 'Excerpt', 'body' => '<p>Body</p>', 'category' => 'intelligenza-artificiale',
            'status' => Article::STATUS_PUBLISHED, 'published_at' => now()->subDay(), 'read_minutes' => 1,
        ]));

        $this->actingAs($this->editor())->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE,
            'article_id' => $article->id,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('search_opportunity_decisions', [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_UPDATE_ARTICLE,
            'article_id' => $article->id,
        ]);
    }

    public function test_create_brief_creates_a_publication_task_under_the_default_editorial_project(): void
    {
        $key = $this->seedZeroResultOpportunity('domanda senza articolo');
        Project::create([
            'title' => 'Editoriale', 'slug' => 'editoriale-decisione-test', 'type' => Project::TYPE_EDITORIAL_SPECIAL,
            'operational_status' => Project::STATUS_IN_PROGRESS, 'is_default_editorial' => true,
        ]);

        $this->actingAs($this->editor())->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_CREATE_BRIEF,
        ])->assertRedirect()->assertSessionHas('status');

        $decision = SearchOpportunityDecision::query()->where('opportunity_key', $key)->first();
        $this->assertNotNull($decision->project_task_id);

        $task = ProjectTask::find($decision->project_task_id);
        $this->assertSame(ProjectTask::TYPE_PUBLICATION, $task->type);
        $this->assertNull($task->article_id);
    }

    public function test_create_brief_fails_closed_without_a_default_editorial_project(): void
    {
        $key = $this->seedZeroResultOpportunity('nessun progetto');

        $this->actingAs($this->editor())->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_CREATE_BRIEF,
        ])->assertSessionHasErrors('decision_type');

        $this->assertDatabaseCount('search_opportunity_decisions', 0);
        $this->assertDatabaseCount('project_tasks', 0);
    }

    public function test_recording_a_decision_for_an_opportunity_no_longer_present_fails_closed(): void
    {
        // Nessuna opportunità seminata: la chiave non corrisponde a
        // nient'altro attualmente calcolabile.
        $this->actingAs($this->editor())->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => 'internal_zero_result_search|mai esistita|',
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
            'rationale' => 'Motivo.',
        ])->assertSessionHasErrors('opportunity_key');

        $this->assertDatabaseCount('search_opportunity_decisions', 0);
    }

    public function test_the_index_page_shows_the_recorded_decision(): void
    {
        $key = $this->seedZeroResultOpportunity('query con decisione visibile');
        $editor = $this->editor();

        $this->actingAs($editor)->post(route('admin.search-opportunities.record-decision'), [
            'opportunity_key' => $key,
            'decision_type' => SearchOpportunityDecision::DECISION_IGNORE,
            'rationale' => 'Motivazione visibile in elenco.',
        ]);

        $this->actingAs($editor)->get(route('admin.search-opportunities'))
            ->assertOk()
            ->assertSee('Ignora')
            ->assertSee('Motivazione visibile in elenco.');
    }
}
