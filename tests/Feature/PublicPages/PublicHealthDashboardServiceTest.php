<?php

namespace Tests\Feature\PublicPages;

use App\Models\NotFoundHit;
use App\Services\LinkHealth\LinkReachabilityAuditService;
use App\Services\MediaLibraryHealthAudit;
use App\Services\PublicPages\PublicHealthDashboardService;
use App\Services\PublicPages\PublicPageSeoAudit;
use App\Services\PublicPages\RedirectAndCanonicalIntegrityAudit;
use App\Services\PublicPages\WcagInternalAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 30 (programma 100-cantieri Kairus). PublicHealthDashboardService
 * aggrega gli audit dei Cantieri 22 (PublicPageSeoAudit), 23
 * (RedirectAndCanonicalIntegrityAudit), 24 (NotFoundHitTracker — qui usato
 * realmente contro il database, essendo una semplice lettura già
 * ampiamente testata altrove), 25 (LinkReachabilityAuditService), 26
 * (MediaLibraryHealthAudit) e 29 (WcagInternalAudit). Ogni test finge i
 * cinque servizi più complessi (mai una seconda regola di dominio,
 * solo un valore fisso di ritorno) per isolare completamente la logica di
 * aggregazione dal comportamento di ciascun audit, già provato dai propri
 * test dedicati.
 */
class PublicHealthDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSeo(array $pages): void
    {
        $fake = new class($pages) extends PublicPageSeoAudit
        {
            public function __construct(private readonly array $pages) {}

            public function audit(): array
            {
                return $this->pages;
            }
        };

        $this->app->instance(PublicPageSeoAudit::class, $fake);
    }

    private function fakeRedirectCanonical(array $redirects, array $canonicals): void
    {
        $fake = new class($redirects, $canonicals) extends RedirectAndCanonicalIntegrityAudit
        {
            public function __construct(private readonly array $redirects, private readonly array $canonicals) {}

            public function auditRedirects(): array
            {
                return $this->redirects;
            }

            public function auditCanonicalConsistency(int $articleLimit = 50): array
            {
                return $this->canonicals;
            }
        };

        $this->app->instance(RedirectAndCanonicalIntegrityAudit::class, $fake);
    }

    private function fakeLinks(array $internal, array $external): void
    {
        $fake = new class($internal, $external) extends LinkReachabilityAuditService
        {
            public function __construct(private readonly array $internal, private readonly array $external) {}

            public function audit(int $articleLimit = 50, bool $checkExternal = false): array
            {
                return ['internal' => $this->internal, 'external' => $this->external];
            }
        };

        $this->app->instance(LinkReachabilityAuditService::class, $fake);
    }

    private function fakeMedia(array $result): void
    {
        $fake = new class($result) extends MediaLibraryHealthAudit
        {
            public function __construct(private readonly array $result) {}

            public function audit(?int $maxRecommendedSizeBytes = null): array
            {
                return $this->result;
            }
        };

        $this->app->instance(MediaLibraryHealthAudit::class, $fake);
    }

    private function fakeWcag(array $pages): void
    {
        $fake = new class($pages) extends WcagInternalAudit
        {
            public function __construct(private readonly array $pages) {}

            public function audit(): array
            {
                return $this->pages;
            }
        };

        $this->app->instance(WcagInternalAudit::class, $fake);
    }

    private function emptyMediaResult(): array
    {
        return ['analyzed' => 0, 'rows' => [], 'missing_alt' => 0, 'missing_credit' => 0, 'missing_file' => 0, 'oversized' => 0, 'non_optimal_format' => 0];
    }

    public function test_the_snapshot_is_sana_when_every_domain_has_no_findings(): void
    {
        $this->fakeSeo([['key' => 'home', 'label' => 'Home', 'route_name' => 'home', 'kind' => 'static', 'sample_url' => 'http://x/test', 'checked' => true, 'http_status' => 200, 'title_present' => true, 'description_present' => true, 'canonical' => 'http://x/test', 'robots' => null, 'json_ld_blocks' => 1, 'findings' => []]]);
        $this->fakeRedirectCanonical([], []);
        $this->fakeLinks([], []);
        $this->fakeMedia($this->emptyMediaResult());
        $this->fakeWcag([['key' => 'home', 'label' => 'Home', 'url' => 'http://x/test', 'http_status' => 200, 'findings' => []]]);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame('SANA', $snapshot['status']);
        $this->assertSame(0, $snapshot['open_findings_total']);
        $this->assertSame([], $snapshot['domains']['seo']['flagged']);
        $this->assertSame([], $snapshot['domains']['wcag']['flagged']);
    }

    public function test_a_finding_in_every_audited_domain_is_counted_in_the_total(): void
    {
        $this->fakeSeo([
            ['key' => 'home', 'label' => 'Home', 'route_name' => 'home', 'kind' => 'static', 'sample_url' => 'http://x/home', 'checked' => true, 'http_status' => 500, 'title_present' => null, 'description_present' => null, 'canonical' => null, 'robots' => null, 'json_ld_blocks' => null, 'findings' => ['Stato HTTP inatteso: 500.']],
        ]);
        $this->fakeRedirectCanonical(
            [['old_slug' => 'vecchio', 'article_id' => 1, 'http_status' => 404, 'findings' => ['Redirect rotto.']]],
            [['type' => 'categoria', 'url' => 'http://x/cat', 'http_status' => 200, 'findings' => ['Canonical incoerente.']]],
        );
        $this->fakeLinks(
            [['url' => '/rotto', 'articles' => ['a'], 'http_status' => 404, 'findings' => ['Stato HTTP inatteso: 404.']]],
            [['url' => 'https://esterno.test', 'articles' => ['a'], 'reachable' => null, 'findings' => []]],
        );
        $this->fakeMedia([
            'analyzed' => 1,
            'rows' => [['id' => 1, 'filename' => 'foto.jpg', 'disk_name' => 'foto.jpg', 'findings' => ['Testo alternativo mancante.']]],
            'missing_alt' => 1, 'missing_credit' => 0, 'missing_file' => 0, 'oversized' => 0, 'non_optimal_format' => 0,
        ]);
        $this->fakeWcag([
            ['key' => 'contatti', 'label' => 'Contatti', 'url' => 'http://x/contatti', 'http_status' => 200, 'findings' => ['Nessun <h1> in pagina.']],
        ]);

        NotFoundHit::create([
            'path_hash' => hash('sha256', '/vecchio-path'),
            'path' => '/vecchio-path',
            'hits' => 3,
            'last_referer' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame('DA_RIVEDERE', $snapshot['status']);
        // 1 seo + 1 redirect + 1 canonical + 1 link interno + 1 media + 1 wcag + 1 not-found = 7.
        $this->assertSame(7, $snapshot['open_findings_total']);
        $this->assertCount(1, $snapshot['domains']['seo']['flagged']);
        $this->assertCount(1, $snapshot['domains']['redirects_canonical']['flagged_redirects']);
        $this->assertCount(1, $snapshot['domains']['redirects_canonical']['flagged_canonicals']);
        $this->assertCount(1, $snapshot['domains']['links']['flagged']);
        $this->assertSame(1, $snapshot['domains']['links']['external_links_found']);
        $this->assertCount(1, $snapshot['domains']['media']['flagged']);
        $this->assertCount(1, $snapshot['domains']['wcag']['flagged']);
        $this->assertSame(1, $snapshot['domains']['not_found']['finding_count']);
    }

    public function test_performance_and_keyboard_navigation_are_reported_unavailable_and_excluded_from_the_total(): void
    {
        $this->fakeSeo([]);
        $this->fakeRedirectCanonical([], []);
        $this->fakeLinks([], []);
        $this->fakeMedia($this->emptyMediaResult());
        $this->fakeWcag([]);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertFalse($snapshot['domains']['performance']['available']);
        $this->assertFalse($snapshot['domains']['keyboard_navigation']['available']);
        $this->assertSame(0, $snapshot['open_findings_total']);
    }
}
