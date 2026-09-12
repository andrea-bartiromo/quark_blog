<?php

namespace Tests\Feature\LinkHealth;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Services\LinkHealth\LinkReachabilityAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cantiere 25 (programma 100-cantieri Kairus). LinkReachabilityAuditService
 * copre i collegamenti nel corpo di un articolo che
 * App\Services\InternalLinking\InternalLinkAuditService NON copre:
 * interni diversi da /articolo/, ed esterni.
 */
class LinkReachabilityAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(string $body, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => $body,
            'category' => 'fisica',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'read_minutes' => 3,
            'verification_status' => 'unverified',
        ], $overrides));
    }

    public function test_a_working_internal_link_to_a_category_has_no_findings(): void
    {
        Category::create(['name' => 'Fisica', 'slug' => 'fisica-audit-25', 'is_active' => true, 'status' => Category::STATUS_PUBLISHED]);

        $this->publishedArticle('<p>Vedi la <a href="'.route('categoria', ['slug' => 'fisica-audit-25']).'">categoria</a>.</p>');

        $result = app(LinkReachabilityAuditService::class)->audit();

        $this->assertCount(1, $result['internal']);
        $this->assertSame([], $result['internal'][0]['findings']);
        $this->assertSame(200, $result['internal'][0]['http_status']);
    }

    public function test_a_broken_internal_link_is_flagged(): void
    {
        $this->publishedArticle('<p>Vedi il <a href="/percorsi/questo-percorso-non-esiste-affatto">percorso</a>.</p>');

        $result = app(LinkReachabilityAuditService::class)->audit();

        $this->assertCount(1, $result['internal']);
        $this->assertNotSame([], $result['internal'][0]['findings']);
        $this->assertSame(404, $result['internal'][0]['http_status']);
    }

    /**
     * App\Services\InternalLinking\InternalLinkAuditService già classifica
     * ogni /articolo/{slug} (compresi i rotti) con la sua stessa logica di
     * risoluzione redirect — riprodurlo qui duplicherebbe un controllo
     * esistente con un risultato meno accurato.
     */
    public function test_article_links_are_never_included_since_they_are_already_covered_elsewhere(): void
    {
        $this->publishedArticle('<p><a href="/articolo/questo-slug-non-esiste">link rotto</a></p>');

        $result = app(LinkReachabilityAuditService::class)->audit();

        $this->assertSame([], $result['internal']);
    }

    public function test_the_same_internal_url_referenced_by_multiple_articles_is_aggregated_into_one_row(): void
    {
        Category::create(['name' => 'Fisica', 'slug' => 'fisica-audit-25b', 'is_active' => true, 'status' => Category::STATUS_PUBLISHED]);
        $url = route('categoria', ['slug' => 'fisica-audit-25b']);

        $this->publishedArticle('<a href="'.$url.'">uno</a>', ['slug' => 'articolo-uno-'.uniqid()]);
        $this->publishedArticle('<a href="'.$url.'">due</a>', ['slug' => 'articolo-due-'.uniqid()]);

        $result = app(LinkReachabilityAuditService::class)->audit();

        $this->assertCount(1, $result['internal']);
        $this->assertCount(2, $result['internal'][0]['articles']);
    }

    public function test_external_links_are_listed_but_not_checked_by_default(): void
    {
        Http::fake();

        $this->publishedArticle('<a href="https://esempio-esterno.it/fonte">fonte</a>');

        $result = app(LinkReachabilityAuditService::class)->audit();

        $this->assertCount(1, $result['external']);
        $this->assertNull($result['external'][0]['reachable']);
        Http::assertNothingSent();
    }

    public function test_a_reachable_external_link_is_confirmed_when_check_external_is_enabled(): void
    {
        Http::fake(['https://esempio-esterno.it/*' => Http::response('', 200)]);

        $this->publishedArticle('<a href="https://esempio-esterno.it/fonte">fonte</a>');

        $result = app(LinkReachabilityAuditService::class)->audit(checkExternal: true);

        $this->assertTrue($result['external'][0]['reachable']);
        $this->assertSame([], $result['external'][0]['findings']);
    }

    public function test_an_unreachable_external_link_is_flagged_when_check_external_is_enabled(): void
    {
        Http::fake(['https://esempio-esterno.it/*' => Http::response('', 404)]);

        $this->publishedArticle('<a href="https://esempio-esterno.it/fonte-morta">fonte</a>');

        $result = app(LinkReachabilityAuditService::class)->audit(checkExternal: true);

        $this->assertFalse($result['external'][0]['reachable']);
        $this->assertNotSame([], $result['external'][0]['findings']);
    }

    public function test_a_connection_exception_on_an_external_link_is_flagged_not_thrown(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->publishedArticle('<a href="https://esempio-esterno-irraggiungibile.it/">fonte</a>');

        $result = app(LinkReachabilityAuditService::class)->audit(checkExternal: true);

        $this->assertFalse($result['external'][0]['reachable']);
        $this->assertNotSame([], $result['external'][0]['findings']);
    }

    public function test_falls_back_to_get_when_the_external_server_rejects_head(): void
    {
        Http::fake(function (ClientRequest $request) {
            return $request->method() === 'HEAD'
                ? Http::response('', 405)
                : Http::response('ok', 200);
        });

        $this->publishedArticle('<a href="https://esempio-esterno.it/solo-get">fonte</a>');

        $result = app(LinkReachabilityAuditService::class)->audit(checkExternal: true);

        $this->assertTrue($result['external'][0]['reachable']);
    }

    public function test_the_article_limit_option_is_respected(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->publishedArticle('<a href="https://esempio-esterno.it/'.$i.'">fonte</a>', ['slug' => 'articolo-limite-'.$i.'-'.uniqid()]);
        }

        $result = app(LinkReachabilityAuditService::class)->audit(articleLimit: 1);

        $this->assertCount(1, $result['external']);
    }
}
