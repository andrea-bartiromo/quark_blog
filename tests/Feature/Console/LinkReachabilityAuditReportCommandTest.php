<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cantiere 25 (programma 100-cantieri Kairus): content:link-reachability-audit
 * è di sola lettura — non è un gate di rilascio.
 */
class LinkReachabilityAuditReportCommandTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(string $body): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-cmd-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => $body,
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ]);
    }

    public function test_reports_no_links_by_default(): void
    {
        $this->artisan('content:link-reachability-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun collegamento interno')
            ->expectsOutputToContain('Nessun collegamento esterno');
    }

    public function test_does_not_make_any_real_http_request_without_check_external(): void
    {
        Http::fake();

        $this->publishedArticle('<a href="https://esempio-esterno.it/fonte">fonte</a>');

        $this->artisan('content:link-reachability-audit')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_check_external_option_performs_a_fake_http_request_in_tests(): void
    {
        Http::fake(['https://esempio-esterno.it/*' => Http::response('', 200)]);

        $this->publishedArticle('<a href="https://esempio-esterno.it/fonte">fonte</a>');

        $this->artisan('content:link-reachability-audit', ['--check-external' => true])
            ->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'esempio-esterno.it'));
    }

    public function test_fails_when_a_broken_internal_link_is_found(): void
    {
        $this->publishedArticle('<a href="/percorsi/questo-percorso-non-esiste-affatto">percorso</a>');

        $this->artisan('content:link-reachability-audit')->assertExitCode(1);
    }

    public function test_json_output_is_produced_successfully(): void
    {
        $this->artisan('content:link-reachability-audit', ['--json' => true])->assertExitCode(0);
    }
}
