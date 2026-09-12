<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\User;
use App\Services\PublicPages\InProcessPageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Cantiere 23 (programma 100-cantieri Kairus): pages:redirect-canonical-audit
 * è di sola lettura — non è un gate di rilascio, è un catalogo di
 * findings per un editore/operatore.
 */
class RedirectAndCanonicalIntegrityAuditReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_no_broken_redirects_and_no_canonical_issues_by_default(): void
    {
        $this->artisan('pages:redirect-canonical-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun vecchio slug articolo con redirect registrato.')
            ->expectsOutputToContain('Nessuna incoerenza rilevata tra le pagine verificate.');
    }

    public function test_fails_when_a_redirect_is_broken(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo rinominato',
            'slug' => 'slug-vecchio-cmd',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
        $article->update(['slug' => 'slug-nuovo-cmd']);
        $article->update(['status' => Article::STATUS_DRAFT]);

        // Un redirect verso un articolo non più pubblicato risponde 404:
        // stato atteso, nessun finding, exit 0.
        $this->artisan('pages:redirect-canonical-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Tutti i redirect registrati sono coerenti.');
    }

    public function test_json_output_is_produced_successfully(): void
    {
        $this->artisan('pages:redirect-canonical-audit', ['--json' => true])->assertExitCode(0);
    }

    /**
     * Codex (PR #571): il ramo --json calcolava lo stato di uscita PRIMA
     * di valutare i findings, restituendo sempre SUCCESS: un chiamante
     * JSON non poteva mai rilevare un'incoerenza reale tramite exit code.
     */
    public function test_json_output_fails_when_a_redirect_has_a_real_finding(): void
    {
        $author = User::factory()->create(['role' => 'author']);
        $article = Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo rinominato json',
            'slug' => 'slug-vecchio-json',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
        $article->update(['slug' => 'slug-nuovo-json']);

        $fake = new class extends InProcessPageFetcher
        {
            public function fetch(string $url): Response
            {
                return new Response('errore', 500);
            }
        };
        $this->app->instance(InProcessPageFetcher::class, $fake);

        $this->artisan('pages:redirect-canonical-audit', ['--json' => true])->assertExitCode(1);
    }

    public function test_limit_option_is_reflected_in_the_output(): void
    {
        $this->artisan('pages:redirect-canonical-audit', ['--limit' => 5])
            ->assertExitCode(0)
            ->expectsOutputToContain('ai 5 più recenti');
    }
}
