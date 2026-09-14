<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Cantiere 84 (programma "100 cantieri Kairus", indipendente):
 * `content-sources:radar` è solo il wrapper a comando di
 * ContentSourcesRadarService (già coperto in dettaglio da
 * ContentSourcesRadarServiceTest) — questi test verificano solo che il
 * comando esponga fedelmente il servizio, in sola lettura.
 */
class ContentSourcesRadarAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova.',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
        ], $overrides));
    }

    public function test_json_output_matches_the_service_report_structure(): void
    {
        $this->article(['primary_sources' => 'https://nature.com/a']);
        $this->article(['primary_sources' => null]);

        $exitCode = Artisan::call('content-sources:radar --json');
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertSame(2, $output['total_published_articles']);
        $this->assertSame(1, $output['articles_with_sources']);
        $this->assertSame(1, $output['articles_without_sources']);
        $this->assertSame(1, $output['distinct_domains']);
        $this->assertSame('nature.com', $output['top_domains'][0]['domain']);
    }

    /**
     * Codex (PR #609): la classificazione "fonti nel corpo" (heading
     * manuale o blocco legacy dopo "---") è un ramo distinto dal
     * conteggio domini — verificato qui a livello di comando, oltre al
     * dettaglio già coperto da ContentSourcesRadarServiceTest.
     */
    public function test_json_output_reports_body_only_sources_separately_from_link_sources(): void
    {
        $this->article([
            'primary_sources' => null,
            'body' => '<p>Corpo.</p><h2>Fonti</h2><p>Intervista diretta.</p>',
        ]);

        Artisan::call('content-sources:radar --json');
        $output = json_decode(Artisan::output(), true);

        $this->assertSame(1, $output['articles_with_sources']);
        $this->assertSame(1, $output['articles_with_body_only_sources']);
        $this->assertSame(0, $output['distinct_domains']);
    }

    public function test_text_report_mentions_the_top_domain_and_zero_source_count(): void
    {
        $this->article(['primary_sources' => 'https://nature.com/a']);
        $this->article(['primary_sources' => null]);

        $this->artisan('content-sources:radar')
            ->expectsOutputToContain('RADAR FONTI')
            ->expectsOutputToContain('nature.com')
            ->expectsOutputToContain('Senza alcuna fonte: 1')
            ->assertSuccessful();
    }

    public function test_the_command_never_writes_to_the_database(): void
    {
        $article = $this->article(['primary_sources' => 'https://nature.com/a']);
        $originalUpdatedAt = $article->updated_at;

        $this->artisan('content-sources:radar --json')->assertSuccessful();
        $this->artisan('content-sources:radar')->assertSuccessful();

        $this->assertEquals($originalUpdatedAt, $article->fresh()->updated_at);
        $this->assertSame('https://nature.com/a', $article->fresh()->primary_sources);
    }

    public function test_an_empty_catalog_produces_a_clean_report_without_errors(): void
    {
        $this->artisan('content-sources:radar')
            ->expectsOutputToContain('Nessun dominio citato al momento.')
            ->assertSuccessful();
    }
}
