<?php

namespace Tests\Feature\SearchConsole;

use App\Models\Article;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Services\SearchConsole\SearchConsoleCsvImporter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchConsoleCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gsc_test_').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_imports_valid_rows_and_matches_an_article(): void
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
            ."onde gravitazionali,https://kairus.it/articolo/onde-gravitazionali,12,300,4.00%,3.5\n"
            ."fisica quantistica,https://kairus.it/notizie,2,150,1.33%,15.2\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(2, $result->imported);
        $this->assertSame(1, $result->matchedToArticle);
        $this->assertSame(1, $result->unmatched);
        $this->assertEmpty($result->errors);

        $row = SearchConsoleQuery::where('query', 'onde gravitazionali')->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->article_id);
        $this->assertSame(12, $row->clicks);
        $this->assertSame(300, $row->impressions);
        $this->assertEqualsWithDelta(0.04, $row->ctr, 0.0001);
        $this->assertEqualsWithDelta(3.5, $row->position, 0.01);
    }

    public function test_accepts_a_raw_decimal_ctr_as_well_as_a_percentage_string(): void
    {
        $csv = "query,page,clicks,impressions,ctr,position\n"
            ."test,https://kairus.it/notizie,1,50,0.02,8\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(1, $result->imported);
        $this->assertEqualsWithDelta(0.02, SearchConsoleQuery::first()->ctr, 0.0001);
    }

    public function test_rejects_a_csv_missing_required_columns(): void
    {
        $csv = "query,clicks,impressions\ntest,1,50\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(0, $result->imported);
        $this->assertNotEmpty($result->errors);
        $this->assertSame(0, SearchConsoleQuery::count());
    }

    public function test_skips_malformed_rows_but_imports_the_valid_ones(): void
    {
        $csv = "query,page,clicks,impressions,ctr,position\n"
            ."valida,https://kairus.it/notizie,5,100,5.0%,2\n"
            .",https://kairus.it/notizie,5,100,5.0%,2\n"
            ."senza numeri,https://kairus.it/notizie,not-a-number,100,5.0%,2\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(1, $result->imported);
        $this->assertCount(2, $result->errors);
    }

    public function test_reimporting_the_same_period_replaces_instead_of_duplicating(): void
    {
        $csvFirst = "query,page,clicks,impressions,ctr,position\nvecchia query,https://kairus.it/notizie,1,10,1%,5\n";
        $csvSecond = "query,page,clicks,impressions,ctr,position\nnuova query,https://kairus.it/notizie,2,20,2%,4\n";

        $importer = app(SearchConsoleCsvImporter::class);
        $importer->import($this->writeCsv($csvFirst), Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));
        $importer->import($this->writeCsv($csvSecond), Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(1, SearchConsoleQuery::count());
        $this->assertSame('nuova query', SearchConsoleQuery::first()->query);
    }

    public function test_a_second_period_does_not_disturb_an_existing_one(): void
    {
        $csvFirst = "query,page,clicks,impressions,ctr,position\nperiodo uno,https://kairus.it/notizie,1,10,1%,5\n";
        $csvSecond = "query,page,clicks,impressions,ctr,position\nperiodo due,https://kairus.it/notizie,2,20,2%,4\n";

        $importer = app(SearchConsoleCsvImporter::class);
        $importer->import($this->writeCsv($csvFirst), Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));
        $importer->import($this->writeCsv($csvSecond), Carbon::parse('2026-08-08'), Carbon::parse('2026-08-14'));

        $this->assertSame(2, SearchConsoleQuery::count());
    }

    public function test_empty_file_is_rejected_cleanly(): void
    {
        $path = $this->writeCsv('');
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(0, $result->imported);
        $this->assertNotEmpty($result->errors);
    }

    public function test_negative_metrics_are_rejected_as_malformed(): void
    {
        $csv = "query,page,clicks,impressions,ctr,position\nquery negativa,https://kairus.it/notizie,-1,100,5%,2\n";

        $path = $this->writeCsv($csv);
        $result = app(SearchConsoleCsvImporter::class)->import($path, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-07'));

        $this->assertSame(0, $result->imported);
    }
}
