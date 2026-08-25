<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\SearchOpportunityStatus;
use App\Models\SearchZeroResultQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchOpportunityScoringService;
use App\Services\SearchConsole\SearchOpportunityStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SearchOpportunityControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.search-opportunities'))->assertRedirect(route('login'));
    }

    public function test_index_shows_empty_state_when_nothing_imported(): void
    {
        $this->actingAs($this->editor())
            ->get(route('admin.search-opportunities'))
            ->assertOk()
            ->assertSee('Nessun dato Search Console importato');
    }

    public function test_index_lists_opportunities_for_the_latest_period(): void
    {
        SearchConsoleQuery::create([
            'query' => 'query interessante',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 200,
            'ctr' => 0.001,
            'position' => 25,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-1',
            'imported_at' => now(),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities'));

        $response->assertOk();
        $response->assertSee('query interessante');
    }

    /**
     * Missione 45 (secondo batch autonomo KAIRUS, Fase F — Search
     * Intelligence): "import freshness" — la cronologia import (periodi
     * diversi accumulati nel tempo, mai mostrati prima d'ora) deve
     * comparire davvero sulla pagina reale.
     */
    public function test_index_shows_the_import_history_across_multiple_periods(): void
    {
        SearchConsoleQuery::create([
            'query' => 'batch vecchio',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 10,
            'ctr' => 0.1,
            'position' => 5,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-07',
            'import_batch' => 'batch-vecchio-http',
            'imported_at' => now()->subDays(10),
        ]);
        SearchConsoleQuery::create([
            'query' => 'batch recente',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 10,
            'ctr' => 0.1,
            'position' => 5,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-recente-http',
            'imported_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities'));

        $response->assertOk();
        $response->assertSee('Cronologia import (2)');
        $response->assertSee('01/07/2026');
        $response->assertSee('01/08/2026');
    }

    public function test_index_can_be_filtered_by_type(): void
    {
        SearchConsoleQuery::create([
            'query' => 'alta impression basso ctr',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 200,
            'ctr' => 0.001,
            'position' => 25,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-1',
            'imported_at' => now(),
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities', ['tipo' => 'near_page_one']));

        $response->assertOk();
        // Il tipo effettivo generato da questa riga e' high_impression_low_ctr,
        // non near_page_one: filtrando per near_page_one la lista deve
        // risultare vuota.
        $response->assertSee('Nessuna opportunità trovata');
    }

    public function test_an_unknown_type_filter_is_ignored_silently(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities', ['tipo' => 'tipo-inventato']));

        $response->assertOk();
    }

    /**
     * Missione 46 (secondo batch autonomo KAIRUS, Fase F — Search
     * Intelligence): "opportunity lifecycle filter" — lo stato è già
     * impostabile per riga (updateStatus()) e già mostrato, ma nessun
     * filtro esisteva mai per nasconderlo dall'elenco. Stesso identico
     * pattern del filtro `tipo` già testato sopra.
     */
    public function test_index_can_be_filtered_by_status(): void
    {
        $editor = $this->editor();

        // Collegata a un articolo esistente: evita che la stessa riga generi
        // *anche* un'opportunità no_strong_landing_page (stesso testo di
        // query, key diversa), che renderebbe ambiguo l'assertSee/assertDontSee
        // sotto basato sul solo testo della query.
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo per query con stato gestito',
            'slug' => 'articolo-per-query-con-stato-gestito',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        SearchConsoleQuery::create([
            'query' => 'query con stato gestito',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => $article->id,
            'clicks' => 1,
            'impressions' => 200,
            'ctr' => 0.001,
            'position' => 25,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-status-filter',
            'imported_at' => now(),
        ]);

        $opportunity = app(SearchOpportunityScoringService::class)
            ->forPeriod(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'), null, null)
            ->first();

        app(SearchOpportunityStatusService::class)
            ->setStatus($opportunity->key, SearchOpportunityStatus::STATUS_ACTIONED, $editor);

        $actionedResponse = $this->actingAs($editor)->get(route('admin.search-opportunities', ['stato' => 'actioned']));
        $actionedResponse->assertOk();
        $actionedResponse->assertSee('query con stato gestito');

        $newResponse = $this->actingAs($editor)->get(route('admin.search-opportunities', ['stato' => 'new']));
        $newResponse->assertOk();
        $newResponse->assertDontSee('query con stato gestito');
        $newResponse->assertSee('Nessuna opportunità trovata');
    }

    public function test_an_unknown_status_filter_is_ignored_silently(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities', ['stato' => 'stato-inventato']));

        $response->assertOk();
    }

    public function test_import_form_requires_authentication(): void
    {
        $this->get(route('admin.search-opportunities.import-form'))->assertRedirect(route('login'));
    }

    public function test_a_valid_csv_import_creates_rows_and_redirects_with_a_status_message(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        Article::create([
            'user_id' => $author->id,
            'title' => 'Onde gravitazionali',
            'slug' => 'onde-gravitazionali',
            'body' => 'Corpo.',
            'category' => 'spazio',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $csv = "query,page,clicks,impressions,ctr,position\n"
            ."onde gravitazionali,https://kairus.it/articolo/onde-gravitazionali,12,300,4.00%,3.5\n";
        $path = tempnam(sys_get_temp_dir(), 'gsc_upload_').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'export.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->editor())->post(route('admin.search-opportunities.import'), [
            'csv' => $file,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
        ]);

        $response->assertRedirect(route('admin.search-opportunities'));
        $this->assertSame(1, SearchConsoleQuery::count());
    }

    public function test_a_non_csv_file_is_rejected_by_validation(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'not_csv_').'.jpg';
        file_put_contents($path, 'not really an image, just wrong extension');
        $file = new UploadedFile($path, 'export.jpg', 'image/jpeg', null, true);

        $response = $this->actingAs($this->editor())->post(route('admin.search-opportunities.import'), [
            'csv' => $file,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
        ]);

        $response->assertSessionHasErrors('csv');
        $this->assertSame(0, SearchConsoleQuery::count());
    }

    // ── Mission 32 — Search Opportunity Pipeline ────────────────────────

    public function test_internal_zero_result_opportunities_show_even_without_any_search_console_import(): void
    {
        SearchZeroResultQuery::create([
            'normalized_query' => 'buco nero rotante',
            'hit_count' => SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities'));

        $response->assertOk();
        $response->assertSee('buco nero rotante');
        $response->assertSee('Ricerca interna senza risultati');
        $response->assertDontSee('Nessun dato Search Console importato finora.');
    }

    public function test_index_can_be_filtered_to_only_internal_zero_result_opportunities(): void
    {
        SearchConsoleQuery::create([
            'query' => 'alta impression basso ctr',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 1,
            'impressions' => 200,
            'ctr' => 0.001,
            'position' => 25,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-1',
            'imported_at' => now(),
        ]);
        SearchZeroResultQuery::create([
            'normalized_query' => 'solo ricerca interna',
            'hit_count' => SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities', [
            'tipo' => SearchOpportunityScoringService::TYPE_INTERNAL_ZERO_RESULT_SEARCH,
        ]));

        $response->assertOk();
        $response->assertSee('solo ricerca interna');
        $response->assertDontSee('alta impression basso ctr');
    }

    /**
     * "Avoid duplicate opportunity creation": la stessa query segnalata sia
     * da Search Console (no_strong_landing_page) sia dalla diagnostica
     * interna deve comparire una sola volta nella pagina.
     */
    public function test_a_query_flagged_by_both_sources_appears_only_once(): void
    {
        SearchConsoleQuery::create([
            'query' => 'argomento condiviso',
            'page_url' => 'https://kairus.it/notizie',
            'article_id' => null,
            'clicks' => 0,
            'impressions' => 40,
            'ctr' => 0.0,
            'position' => 30,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-07',
            'import_batch' => 'batch-1',
            'imported_at' => now(),
        ]);
        SearchZeroResultQuery::create([
            'normalized_query' => 'argomento condiviso',
            'hit_count' => SearchOpportunityScoringService::MIN_INTERNAL_ZERO_RESULT_HITS,
        ]);

        $response = $this->actingAs($this->editor())->get(route('admin.search-opportunities'));

        $response->assertOk();
        $response->assertSee('Nessuna landing page dedicata');
        // "Ricerca interna senza risultati" resta nel <select> dei filtri
        // (elenco statico di tutti i tipi, indipendente dai dati): la prova
        // che nessuna riga duplicata sia stata generata è l'assenza della
        // spiegazione specifica di quel tipo, mai presente altrove.
        $response->assertDontSee('ricerche interne su Kairus');
    }

    public function test_period_end_before_period_start_is_rejected(): void
    {
        $csv = "query,page,clicks,impressions,ctr,position\ntest,https://kairus.it/notizie,1,50,2%,4\n";
        $path = tempnam(sys_get_temp_dir(), 'gsc_upload_').'.csv';
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'export.csv', 'text/csv', null, true);

        $response = $this->actingAs($this->editor())->post(route('admin.search-opportunities.import'), [
            'csv' => $file,
            'period_start' => '2026-08-07',
            'period_end' => '2026-08-01',
        ]);

        $response->assertSessionHasErrors('period_end');
    }
}
