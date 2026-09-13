<?php

namespace Tests\Feature\SearchConsole;

use App\Models\SearchConsoleCoverageImport;
use App\Models\SearchConsoleCoverageIssue;
use App\Services\SearchConsole\SearchConsoleCoverageCsvImporter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchConsoleCoverageCsvImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://kairus.it',
        ]);
    }

    public function test_it_replaces_an_import_for_the_same_property_and_observation_date(): void
    {
        $first = $this->csv("Ragione,Sorgente,Convalida,Pagine,URL\n\"Rilevata, ma attualmente non indicizzata\",Google,Non iniziata,13,https://kairus.it/notizie\n");
        $second = $this->csv("Ragione,Sorgente,Convalida,Pagine\nPagina con reindirizzamento,Sito web,Non iniziata,5\n");
        $service = app(SearchConsoleCoverageCsvImporter::class);

        $service->import($first, 'sc-domain:kairus.it', Carbon::parse('2026-09-13'));
        $service->import($second, 'sc-domain:kairus.it', Carbon::parse('2026-09-13'));

        $this->assertDatabaseCount('search_console_coverage_imports', 1);
        $this->assertDatabaseCount('search_console_coverage_issues', 1);
        $this->assertSame(SearchConsoleCoverageIssue::CLASS_EXPECTED, SearchConsoleCoverageImport::firstOrFail()->issues()->firstOrFail()->classification);
    }

    public function test_it_classifies_indexing_rows_for_editorial_review_and_expected_exclusions_without_side_effects(): void
    {
        $file = $this->csv("Ragione,Sorgente,Convalida,Pagine,URL\n\"Rilevata, ma attualmente non indicizzata\",Google,Non iniziata,1,https://kairus.it/notizie\n\"Pagina scansionata, ma attualmente non indicizzata\",Google,Non iniziata,1,https://kairus.it/notizie\n\"Esclusa in base al tag noindex\",Sito web,Non iniziata,1,\nPagina con reindirizzamento,Sito web,Non iniziata,5,\n");
        $result = app(SearchConsoleCoverageCsvImporter::class)->import($file, 'sc-domain:kairus.it', Carbon::parse('2026-09-13'));

        $this->assertSame(4, $result['imported']);
        $this->assertSame(2, SearchConsoleCoverageIssue::where('classification', SearchConsoleCoverageIssue::CLASS_EDITORIAL)->count());
        $this->assertSame(1, SearchConsoleCoverageIssue::where('classification', SearchConsoleCoverageIssue::CLASS_INTENTIONAL)->count());
        $this->assertSame(1, SearchConsoleCoverageIssue::where('classification', SearchConsoleCoverageIssue::CLASS_EXPECTED)->count());
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_it_fails_closed_when_a_priority_reason_has_no_public_url_to_compare(): void
    {
        $file = $this->csv("Ragione,Pagine\n\"Rilevata, ma attualmente non indicizzata\",13\n");
        app(SearchConsoleCoverageCsvImporter::class)->import($file, 'sc-domain:kairus.it', Carbon::parse('2026-09-13'));

        $this->assertSame(SearchConsoleCoverageIssue::CLASS_REVIEW, SearchConsoleCoverageIssue::firstOrFail()->classification);
    }

    public function test_it_keeps_aggregate_priority_rows_out_of_editorial_queue(): void
    {
        $file = $this->csv("Reason,Pages,URL\n\"Discovered - currently not indexed\",13,https://kairus.it/notizie\n");
        app(SearchConsoleCoverageCsvImporter::class)->import($file, 'sc-domain:kairus.it', Carbon::parse('2026-09-13'));

        $this->assertSame(SearchConsoleCoverageIssue::CLASS_REVIEW, SearchConsoleCoverageIssue::firstOrFail()->classification);
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'coverage-');
        file_put_contents($path, $contents);
        return $path;
    }
}
