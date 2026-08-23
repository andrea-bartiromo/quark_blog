<?php

namespace Tests\Feature\SearchConsole;

use App\Models\SearchConsoleQuery;
use App\Models\SearchOpportunityStatus;
use App\Models\User;
use App\Services\SearchConsole\SearchConsoleCsvImporter;
use App\Services\SearchConsole\SearchOpportunity;
use App\Services\SearchConsole\SearchOpportunityStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 6 (Search Console Opportunity Intelligence V2): import safety
 * (BOM, locale decimal separators) and the optional editorial status
 * workflow (new/reviewed/actioned/dismissed). Never touches
 * EditorialRadarService (other lane's domain, not yet on main) and never
 * auto-creates articles, auto-modifies SEO copy, or introduces an
 * AI/opaque score.
 */
class SearchOpportunityImportSafetyAndStatusTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gsc_test_').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    // ── Import safety ────────────────────────────────────────────────

    public function test_a_utf8_bom_before_the_header_does_not_break_column_recognition(): void
    {
        $csv = "\xEF\xBB\xBFquery,page,clicks,impressions,ctr,position\n"
            ."fisica quantistica,https://kairus.it/notizie,2,150,1.33%,15.2\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(1, $result->imported);
        $this->assertEmpty($result->errors);
    }

    public function test_a_comma_decimal_separator_in_ctr_and_position_is_accepted(): void
    {
        // Export in locale italiana/europea: "1,33%" e "15,2" invece di
        // "1.33%" e "15.2".
        $csv = "query,page,clicks,impressions,ctr,position\n"
            ."fisica quantistica,https://kairus.it/notizie,2,150,\"1,33%\",\"15,2\"\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(1, $result->imported);
        $this->assertEmpty($result->errors);

        $row = SearchConsoleQuery::first();
        $this->assertEqualsWithDelta(0.0133, $row->ctr, 0.0001);
        $this->assertEqualsWithDelta(15.2, $row->position, 0.01);
    }

    public function test_an_ambiguous_value_with_both_comma_and_dot_is_left_alone_and_rejected_if_invalid(): void
    {
        // "1.234,56" (formato europeo con separatore delle migliaia) non
        // viene interpretato a caso: la normalizzazione si applica solo
        // quando c'e' ESATTAMENTE una virgola e nessun punto. Un valore di
        // posizione realistico non ha mai bisogno di un separatore delle
        // migliaia, quindi questo resta scartato come qualunque altro
        // valore non valido — mai un'interpretazione indovinata.
        $csv = "query,page,clicks,impressions,ctr,position\n"
            ."query ambigua,https://kairus.it/notizie,2,150,4.00%,\"1.234,56\"\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(0, $result->imported);
        $this->assertNotEmpty($result->errors);
    }

    // ── Editorial status workflow ────────────────────────────────────

    public function test_a_new_opportunity_defaults_to_no_persisted_status(): void
    {
        $opportunity = new SearchOpportunity(
            type: 'high_impression_low_ctr',
            query: 'fisica quantistica',
            article: null,
            impressions: 500,
            clicks: 5,
            ctr: 0.01,
            position: 3.0,
            score: 42.0,
            explanation: 'Test.',
            pageUrl: 'https://kairus.it/notizie',
        );

        $statuses = app(SearchOpportunityStatusService::class)->statusesFor(collect([$opportunity]));

        $this->assertSame([], $statuses);
    }

    public function test_setting_a_status_persists_it_and_records_the_actor(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $opportunity = new SearchOpportunity(
            type: 'high_impression_low_ctr',
            query: 'fisica quantistica',
            article: null,
            impressions: 500,
            clicks: 5,
            ctr: 0.01,
            position: 3.0,
            score: 42.0,
            explanation: 'Test.',
            pageUrl: 'https://kairus.it/notizie',
        );

        app(SearchOpportunityStatusService::class)->setStatus($opportunity->key, SearchOpportunityStatus::STATUS_REVIEWED, $editor);

        $statuses = app(SearchOpportunityStatusService::class)->statusesFor(collect([$opportunity]));

        $this->assertSame(SearchOpportunityStatus::STATUS_REVIEWED, $statuses[$opportunity->key]);
        $this->assertDatabaseHas('search_opportunity_statuses', [
            'opportunity_key' => $opportunity->key,
            'status' => SearchOpportunityStatus::STATUS_REVIEWED,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_setting_a_status_twice_updates_the_same_row_not_a_duplicate(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $service = app(SearchOpportunityStatusService::class);

        $service->setStatus('high_impression_low_ctr|fisica quantistica|https://kairus.it/notizie', SearchOpportunityStatus::STATUS_REVIEWED, $editor);
        $service->setStatus('high_impression_low_ctr|fisica quantistica|https://kairus.it/notizie', SearchOpportunityStatus::STATUS_ACTIONED, $editor);

        $this->assertDatabaseCount('search_opportunity_statuses', 1);
        $this->assertDatabaseHas('search_opportunity_statuses', ['status' => SearchOpportunityStatus::STATUS_ACTIONED]);
    }

    public function test_two_distinct_opportunity_types_for_the_same_query_and_page_never_collide(): void
    {
        // La chiave include il tipo: due opportunità diverse sulla stessa
        // combinazione query+pagina (es. "vicino a pagina 1" E "CTR basso"
        // nello stesso periodo, in teoria mutuamente esclusive nello
        // scoring attuale ma non garantito per costruzione) restano righe
        // di stato indipendenti.
        $editor = User::factory()->create(['role' => 'editor']);
        $service = app(SearchOpportunityStatusService::class);

        $service->setStatus('near_page_one|fisica quantistica|https://kairus.it/notizie', SearchOpportunityStatus::STATUS_DISMISSED, $editor);
        $service->setStatus('high_impression_low_ctr|fisica quantistica|https://kairus.it/notizie', SearchOpportunityStatus::STATUS_ACTIONED, $editor);

        $this->assertDatabaseCount('search_opportunity_statuses', 2);
    }

    public function test_admin_can_update_an_opportunity_status_via_the_controller(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor)->post(route('admin.search-opportunities.update-status'), [
            'opportunity_key' => 'high_impression_low_ctr|fisica quantistica|https://kairus.it/notizie',
            'status' => SearchOpportunityStatus::STATUS_REVIEWED,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('search_opportunity_statuses', [
            'opportunity_key' => 'high_impression_low_ctr|fisica quantistica|https://kairus.it/notizie',
            'status' => SearchOpportunityStatus::STATUS_REVIEWED,
            'updated_by' => $editor->id,
        ]);
    }

    public function test_an_invalid_status_value_is_rejected(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor)->post(route('admin.search-opportunities.update-status'), [
            'opportunity_key' => 'high_impression_low_ctr|fisica quantistica|https://kairus.it/notizie',
            'status' => 'auto_actioned_by_ai',
        ]);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('search_opportunity_statuses', 0);
    }

    public function test_guests_cannot_update_an_opportunity_status(): void
    {
        $response = $this->post(route('admin.search-opportunities.update-status'), [
            'opportunity_key' => 'high_impression_low_ctr|fisica quantistica|https://kairus.it/notizie',
            'status' => SearchOpportunityStatus::STATUS_REVIEWED,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('search_opportunity_statuses', 0);
    }
}
