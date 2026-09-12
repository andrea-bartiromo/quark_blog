<?php

namespace Tests\Feature\PublicPages;

use App\Models\AuditFindingStatus;
use App\Models\NotFoundHit;
use App\Services\LinkHealth\LinkReachabilityAuditService;
use App\Services\MediaLibraryHealthAudit;
use App\Services\PublicPages\AuditFindingStatusService;
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
 *
 * Cantiere 31: severità (HIGH/MEDIUM) e finding_key per ogni riga
 * segnalata, più lo stato "presa in carico"/"ignorato" letto da
 * AuditFindingStatusService (reale, mai finto: RefreshDatabase basta a
 * isolare i test l'uno dall'altro).
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

    private function fakeEverythingEmpty(): void
    {
        $this->fakeSeo([]);
        $this->fakeRedirectCanonical([], []);
        $this->fakeLinks([], []);
        $this->fakeMedia($this->emptyMediaResult());
        $this->fakeWcag([]);
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
        $this->assertSame(0, $snapshot['high_severity_open_total']);
        $this->assertSame(0, $snapshot['dismissed_findings_total']);
        $this->assertSame([], $snapshot['domains']['seo']['flagged']);
        $this->assertSame([], $snapshot['domains']['wcag']['flagged']);
    }

    public function test_a_finding_in_every_audited_domain_is_counted_in_the_total_with_the_expected_severity(): void
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
        $this->assertSame(0, $snapshot['dismissed_findings_total']);

        $seoRow = $snapshot['domains']['seo']['flagged'][0];
        $this->assertSame('HIGH', $seoRow['severity']); // stato HTTP 500.
        $this->assertSame('seo|home', $seoRow['finding_key']);
        $this->assertSame(AuditFindingStatus::STATUS_NEW, $seoRow['status']);

        $redirectRow = collect($snapshot['domains']['redirects_canonical']['flagged'])->firstWhere('kind', 'redirect');
        $this->assertSame('HIGH', $redirectRow['severity']); // un redirect rotto e' sempre HIGH.
        $this->assertSame('redirects_canonical|redirect:vecchio', $redirectRow['finding_key']);

        $canonicalRow = collect($snapshot['domains']['redirects_canonical']['flagged'])->firstWhere('kind', 'canonical');
        $this->assertSame('MEDIUM', $canonicalRow['severity']); // http_status 200, solo canonical incoerente.

        $notFoundRow = $snapshot['domains']['not_found']['flagged'][0];
        $this->assertSame('MEDIUM', $notFoundRow['severity']); // 3 hits, sotto la soglia HIGH.
        $this->assertSame('not_found|/vecchio-path', $notFoundRow['finding_key']);

        $linkRow = $snapshot['domains']['links']['flagged'][0];
        $this->assertSame('HIGH', $linkRow['severity']); // un link interno rotto e' sempre HIGH.
        $this->assertSame(1, $snapshot['domains']['links']['external_links_found']);

        $mediaRow = $snapshot['domains']['media']['flagged'][0];
        $this->assertSame('MEDIUM', $mediaRow['severity']); // solo alt mancante, nessun file assente.
        $this->assertSame('media|1', $mediaRow['finding_key']);

        $wcagRow = $snapshot['domains']['wcag']['flagged'][0];
        $this->assertSame('MEDIUM', $wcagRow['severity']); // solo struttura heading, nessun landmark/skip-link/lang.
    }

    public function test_a_missing_landmark_or_skip_link_wcag_finding_is_high_severity(): void
    {
        $this->fakeSeo([]);
        $this->fakeRedirectCanonical([], []);
        $this->fakeLinks([], []);
        $this->fakeMedia($this->emptyMediaResult());
        $this->fakeWcag([
            ['key' => 'contatti', 'label' => 'Contatti', 'url' => 'http://x/contatti', 'http_status' => 200, 'findings' => ['Skip-link assente (WCAG 2.4.1).']],
        ]);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame('HIGH', $snapshot['domains']['wcag']['flagged'][0]['severity']);
    }

    public function test_a_missing_file_media_finding_is_high_severity(): void
    {
        $this->fakeSeo([]);
        $this->fakeRedirectCanonical([], []);
        $this->fakeLinks([], []);
        $this->fakeMedia([
            'analyzed' => 1,
            'rows' => [['id' => 5, 'filename' => 'assente.jpg', 'disk_name' => 'assente.jpg', 'findings' => ['File assente su disco (registrato in Libreria media, ma non trovato in public/assets/img).']]],
            'missing_alt' => 0, 'missing_credit' => 0, 'missing_file' => 1, 'oversized' => 0, 'non_optimal_format' => 0,
        ]);
        $this->fakeWcag([]);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame('HIGH', $snapshot['domains']['media']['flagged'][0]['severity']);
    }

    public function test_a_not_found_path_at_or_above_the_hit_threshold_is_high_severity(): void
    {
        $this->fakeEverythingEmpty();
        NotFoundHit::create([
            'path_hash' => hash('sha256', '/molto-visitato'),
            'path' => '/molto-visitato',
            'hits' => 10,
            'last_referer' => null,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame('HIGH', $snapshot['domains']['not_found']['flagged'][0]['severity']);
    }

    /**
     * Cantiere 31: un finding con stato "ignorato" resta visibile
     * (trasparenza) ma esce dal conteggio "aperti" della dashboard —
     * stesso principio già in produzione per search_opportunity_statuses
     * (SearchConsoleOpportunityProvider filtra STATUS_DISMISSED).
     */
    public function test_a_dismissed_finding_is_still_listed_but_excluded_from_open_counts(): void
    {
        $this->fakeSeo([
            ['key' => 'home', 'label' => 'Home', 'route_name' => 'home', 'kind' => 'static', 'sample_url' => 'http://x/home', 'checked' => true, 'http_status' => 500, 'title_present' => null, 'description_present' => null, 'canonical' => null, 'robots' => null, 'json_ld_blocks' => null, 'findings' => ['Stato HTTP inatteso: 500.']],
        ]);
        $this->fakeRedirectCanonical([], []);
        $this->fakeLinks([], []);
        $this->fakeMedia($this->emptyMediaResult());
        $this->fakeWcag([]);

        app(AuditFindingStatusService::class)->setStatus('seo', 'seo|home', AuditFindingStatus::STATUS_DISMISSED, null);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame('SANA', $snapshot['status']);
        $this->assertSame(0, $snapshot['open_findings_total']);
        $this->assertSame(1, $snapshot['dismissed_findings_total']);
        $this->assertSame(0, $snapshot['high_severity_open_total']);
        // La riga resta nell'elenco, solo esclusa dai conteggi.
        $this->assertCount(1, $snapshot['domains']['seo']['flagged']);
        $this->assertSame(AuditFindingStatus::STATUS_DISMISSED, $snapshot['domains']['seo']['flagged'][0]['status']);
        $this->assertSame(0, $snapshot['domains']['seo']['open_count']);
        $this->assertSame(1, $snapshot['domains']['seo']['dismissed_count']);
    }

    public function test_a_finding_taken_in_charge_still_counts_as_open(): void
    {
        $this->fakeSeo([
            ['key' => 'home', 'label' => 'Home', 'route_name' => 'home', 'kind' => 'static', 'sample_url' => 'http://x/home', 'checked' => true, 'http_status' => 500, 'title_present' => null, 'description_present' => null, 'canonical' => null, 'robots' => null, 'json_ld_blocks' => null, 'findings' => ['Stato HTTP inatteso: 500.']],
        ]);
        $this->fakeRedirectCanonical([], []);
        $this->fakeLinks([], []);
        $this->fakeMedia($this->emptyMediaResult());
        $this->fakeWcag([]);

        app(AuditFindingStatusService::class)->setStatus('seo', 'seo|home', AuditFindingStatus::STATUS_IN_CARICO, null);

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame('DA_RIVEDERE', $snapshot['status']);
        $this->assertSame(1, $snapshot['open_findings_total']);
        $this->assertSame(0, $snapshot['dismissed_findings_total']);
        $this->assertSame(AuditFindingStatus::STATUS_IN_CARICO, $snapshot['domains']['seo']['flagged'][0]['status']);
    }

    public function test_performance_and_keyboard_navigation_are_reported_unavailable_and_excluded_from_the_total(): void
    {
        $this->fakeEverythingEmpty();

        $snapshot = app(PublicHealthDashboardService::class)->snapshot();

        $this->assertFalse($snapshot['domains']['performance']['available']);
        $this->assertFalse($snapshot['domains']['keyboard_navigation']['available']);
        $this->assertSame(0, $snapshot['open_findings_total']);
    }

    /**
     * Codex (PR #578, P1): ogni fetch in-process (InProcessPageFetcher)
     * riattraversa il middleware 'web' — incluso StartSession — su una
     * Request sintetica senza cookie, che chiama sempre setId() sulla
     * STESSA istanza Store condivisa con la richiesta reale che ha aperto
     * questa dashboard. Usa i sei servizi REALI (non finti): solo con
     * InProcessPageFetcher che dispatcha davvero attraverso il kernel
     * HTTP si riproduce la rigenerazione dell'id di sessione che il fix
     * deve annullare. Prima del fix, l'id di sessione dopo snapshot()
     * sarebbe quello dell'ULTIMA sotto-richiesta interna, non quello
     * originale.
     */
    public function test_the_session_id_is_unchanged_after_computing_the_snapshot_with_the_real_audits(): void
    {
        $originalId = session()->getId();

        app(PublicHealthDashboardService::class)->snapshot();

        $this->assertSame($originalId, session()->getId());
    }
}
