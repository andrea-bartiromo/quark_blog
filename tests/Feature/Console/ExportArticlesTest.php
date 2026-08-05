<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportArticlesTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function exportPath(): string
    {
        return storage_path('app/exports/articles.csv');
    }

    protected function tearDown(): void
    {
        File::delete($this->exportPath());

        parent::tearDown();
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function test_exports_published_articles_to_csv_with_expected_columns(): void
    {
        $author = $this->author();

        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato',
            'slug' => 'articolo-pubblicato',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('articles:export')->assertExitCode(0);

        $this->assertFileExists($this->exportPath());

        $rows = $this->readCsv($this->exportPath());

        $this->assertSame(['id', 'title', 'slug', 'url', 'category', 'published_at'], $rows[0]);
        $this->assertCount(2, $rows);

        [$id, $title, $slug, $url, $category, $publishedAt] = $rows[1];
        $this->assertSame('Articolo pubblicato', $title);
        $this->assertSame('articolo-pubblicato', $slug);
        $this->assertSame(rtrim(config('app.url'), '/').'/articolo/articolo-pubblicato', $url);
        $this->assertSame('energia', $category);
        $this->assertNotEmpty($publishedAt);
    }

    public function test_url_is_built_from_app_url_config_by_default(): void
    {
        config(['app.url' => 'https://esempio-config.test']);

        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo con config personalizzata',
            'slug' => 'articolo-config',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('articles:export')->assertExitCode(0);

        [, , , $url] = $this->readCsv($this->exportPath())[1];
        $this->assertSame('https://esempio-config.test/articolo/articolo-config', $url);
    }

    public function test_url_is_built_from_app_url_config_even_with_a_trailing_slash(): void
    {
        config(['app.url' => 'https://esempio-config.test/']);

        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo con slash finale',
            'slug' => 'articolo-slash',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('articles:export')->assertExitCode(0);

        [, , , $url] = $this->readCsv($this->exportPath())[1];
        $this->assertSame('https://esempio-config.test/articolo/articolo-slash', $url);
    }

    public function test_base_url_option_overrides_app_url_config(): void
    {
        config(['app.url' => 'https://esempio-config.test']);

        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo con override',
            'slug' => 'articolo-override',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('articles:export', ['--base-url' => 'https://kairus.it'])->assertExitCode(0);

        [, , , $url] = $this->readCsv($this->exportPath())[1];
        $this->assertSame('https://kairus.it/articolo/articolo-override', $url);
    }

    public function test_base_url_option_normalizes_a_trailing_slash(): void
    {
        $author = $this->author();
        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo con override e slash',
            'slug' => 'articolo-override-slash',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $this->artisan('articles:export', ['--base-url' => 'https://kairus.it/'])->assertExitCode(0);

        [, , , $url] = $this->readCsv($this->exportPath())[1];
        $this->assertSame('https://kairus.it/articolo/articolo-override-slash', $url);
    }

    public function test_excludes_draft_review_and_scheduled_articles(): void
    {
        $author = $this->author();

        Article::create([
            'user_id' => $author->id,
            'title' => 'Bozza',
            'slug' => 'bozza',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_DRAFT,
        ]);

        Article::create([
            'user_id' => $author->id,
            'title' => 'In revisione',
            'slug' => 'in-revisione',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_REVIEW,
        ]);

        Article::create([
            'user_id' => $author->id,
            'title' => 'Programmato',
            'slug' => 'programmato',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => now()->addDay(),
        ]);

        $this->artisan('articles:export')->assertExitCode(0);

        $rows = $this->readCsv($this->exportPath());

        // Solo la riga di intestazione: nessun articolo pubblicato.
        $this->assertCount(1, $rows);
    }

    public function test_excludes_published_articles_with_a_future_date(): void
    {
        $author = $this->author();

        Article::create([
            'user_id' => $author->id,
            'title' => 'Pubblicato nel futuro',
            'slug' => 'pubblicato-nel-futuro',
            'body' => 'Corpo.',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->addDay(),
        ]);

        $this->artisan('articles:export')->assertExitCode(0);

        $rows = $this->readCsv($this->exportPath());

        $this->assertCount(1, $rows);
    }

    public function test_exports_multiple_articles(): void
    {
        $author = $this->author();

        foreach (range(1, 3) as $i) {
            Article::create([
                'user_id' => $author->id,
                'title' => "Articolo {$i}",
                'slug' => "articolo-{$i}",
                'body' => 'Corpo.',
                'category' => 'energia',
                'status' => Article::STATUS_PUBLISHED,
                'published_at' => now()->subDays($i),
            ]);
        }

        $this->artisan('articles:export')
            ->expectsOutputToContain('Esportati 3 articoli')
            ->assertExitCode(0);

        $rows = $this->readCsv($this->exportPath());

        $this->assertCount(4, $rows); // intestazione + 3 righe
    }

    public function test_running_with_no_published_articles_still_creates_a_csv_with_only_headers(): void
    {
        $this->artisan('articles:export')
            ->expectsOutputToContain('Esportati 0 articoli')
            ->assertExitCode(0);

        $this->assertFileExists($this->exportPath());

        $rows = $this->readCsv($this->exportPath());

        $this->assertCount(1, $rows);
        $this->assertSame(['id', 'title', 'slug', 'url', 'category', 'published_at'], $rows[0]);
    }
}
