<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\User;
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
