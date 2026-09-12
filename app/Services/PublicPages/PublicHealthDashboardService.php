<?php

namespace App\Services\PublicPages;

use App\Models\NotFoundHit;
use App\Services\LinkHealth\LinkReachabilityAuditService;
use App\Services\MediaLibraryHealthAudit;
use Closure;

/**
 * Cantiere 30 (programma 100-cantieri Kairus), dipende dai Cantieri 22-29.
 * Ispezione preliminare: ognuno di quegli audit (PublicPageSeoAudit,
 * RedirectAndCanonicalIntegrityAudit, NotFoundHitTracker,
 * LinkReachabilityAuditService, MediaLibraryHealthAudit, WcagInternalAudit)
 * è raggiungibile solo da riga di comando — nessuna pagina admin li
 * riassume in un unico posto, a differenza di
 * EditorialOperationsDashboardService (salute EDITORIALE dei contenuti,
 * dominio distinto). Questo servizio riempie quel gap, seguendo lo stesso
 * principio guida: MAI ricalcolare qui una regola già espressa da un
 * audit esistente, solo chiamare, raccogliere e riassumere. Read-only per
 * costruzione: nessun metodo qui scrive sul database.
 *
 * Stessi limiti di default già usati dai comandi CLI corrispondenti (es.
 * 50 articoli più recenti per canonical/link, mai un controllo esterno
 * reale di default) — questa dashboard non introduce una seconda soglia.
 *
 * I Cantieri 27 (baseline performance) e 28 (test browser navigazione da
 * tastiera) non hanno un servizio PHP da aggregare (uno script Node, un
 * file di test Playwright): rappresentati come sezioni "non disponibili
 * qui", con un rimando allo strumento reale — stesso trattamento già
 * riservato a 'distribuzione' in EditorialOperationsDashboardService per
 * un motivo analogo (nessun dato aggregato reale da mostrare).
 */
class PublicHealthDashboardService
{
    private const ARTICLE_LIMIT = 50;

    private const NOT_FOUND_DISPLAY_LIMIT = 50;

    public function __construct(
        private readonly PublicPageSeoAudit $seoAudit,
        private readonly RedirectAndCanonicalIntegrityAudit $redirectCanonicalAudit,
        private readonly NotFoundHitTracker $notFoundHitTracker,
        private readonly LinkReachabilityAuditService $linkAudit,
        private readonly MediaLibraryHealthAudit $mediaAudit,
        private readonly WcagInternalAudit $wcagAudit,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return $this->withPreservedSessionId(function () {
            $domains = [
                'seo' => $this->seoDomain(),
                'redirects_canonical' => $this->redirectsCanonicalDomain(),
                'not_found' => $this->notFoundDomain(),
                'links' => $this->linksDomain(),
                'media' => $this->mediaDomain(),
                'wcag' => $this->wcagDomain(),
                'performance' => $this->performanceDomain(),
                'keyboard_navigation' => $this->keyboardNavigationDomain(),
            ];

            $openFindingsTotal = collect($domains)
                ->where('available', true)
                ->sum('finding_count');

            return [
                'status' => $openFindingsTotal === 0 ? 'SANA' : 'DA_RIVEDERE',
                'open_findings_total' => $openFindingsTotal,
                'domains' => $domains,
            ];
        });
    }

    /**
     * Codex (PR #578, P1): ogni fetch in-process di InProcessPageFetcher
     * riattraversa l'intero gruppo di middleware 'web' — incluso
     * StartSession — su una Request sintetica senza alcun cookie.
     * Illuminate\Session\Middleware\StartSession::getSession() chiama
     * sempre $session->setId(...) sulla STESSA istanza Store condivisa
     * (singleton) già in uso per la richiesta reale che ha aperto questa
     * dashboard, generandole un nuovo id casuale a ogni singola fetch (e
     * ce ne sono decine, una per pagina/link/media verificato). Il
     * cookie di sessione restituito al browser viene scritto SOLO dopo
     * che il controller (e quindi ogni fetch) è già tornato (vedi
     * StartSession::handleStatefulRequest()): senza questo ripristino
     * porterebbe l'id dell'ULTIMA sotto-richiesta invece di quello reale
     * dell'editor — indistinguibile da un logout silenzioso lato browser.
     * Gli attributi di sessione stessi non sono mai a rischio (ogni id
     * generato è sempre nuovo, quindi la lettura dall'handler restituisce
     * sempre un array vuoto che Store::loadSession() fonde senza mai
     * sovrascrivere nulla di esistente) — solo l'id necessita di un
     * ripristino esplicito.
     */
    private function withPreservedSessionId(Closure $callback): array
    {
        $session = session();
        $originalId = $session->getId();

        try {
            return $callback();
        } finally {
            $session->setId($originalId);
        }
    }

    /** @return array<string, mixed> */
    private function seoDomain(): array
    {
        $pages = $this->seoAudit->audit();
        $flagged = collect($pages)->filter(fn (array $p) => $p['findings'] !== [])->values()->all();

        return [
            'label' => 'SEO/canonical/JSON-LD',
            'available' => true,
            'checked_count' => collect($pages)->where('checked', true)->count(),
            'total_count' => count($pages),
            'finding_count' => count($flagged),
            'flagged' => $flagged,
            'cli_hint' => 'php artisan pages:seo-audit',
        ];
    }

    /** @return array<string, mixed> */
    private function redirectsCanonicalDomain(): array
    {
        $redirects = $this->redirectCanonicalAudit->auditRedirects();
        $canonicals = $this->redirectCanonicalAudit->auditCanonicalConsistency(self::ARTICLE_LIMIT);

        $flaggedRedirects = collect($redirects)->filter(fn (array $r) => $r['findings'] !== [])->values()->all();
        $flaggedCanonicals = collect($canonicals)->filter(fn (array $c) => $c['findings'] !== [])->values()->all();

        return [
            'label' => 'Redirect vecchi slug e coerenza canonical',
            'available' => true,
            'checked_count' => count($redirects) + count($canonicals),
            'finding_count' => count($flaggedRedirects) + count($flaggedCanonicals),
            'flagged_redirects' => $flaggedRedirects,
            'flagged_canonicals' => $flaggedCanonicals,
            'cli_hint' => 'php artisan pages:redirect-canonical-audit',
        ];
    }

    /** @return array<string, mixed> */
    private function notFoundDomain(): array
    {
        $topHits = $this->notFoundHitTracker->topHits(self::NOT_FOUND_DISPLAY_LIMIT);

        return [
            'label' => 'Registro 404 dal traffico reale',
            'available' => true,
            'finding_count' => NotFoundHit::query()->count(),
            'flagged' => $topHits,
            'cli_hint' => 'php artisan pages:not-found-registry',
        ];
    }

    /** @return array<string, mixed> */
    private function linksDomain(): array
    {
        $result = $this->linkAudit->audit(self::ARTICLE_LIMIT, checkExternal: false);
        $flagged = collect($result['internal'])->filter(fn (array $r) => $r['findings'] !== [])->values()->all();

        return [
            'label' => 'Collegamenti interni (oltre /articolo/)',
            'available' => true,
            'checked_count' => count($result['internal']),
            'finding_count' => count($flagged),
            'flagged' => $flagged,
            // I collegamenti esterni non sono mai verificati di default
            // (vedi LinkReachabilityAuditService, stessa scelta del comando
            // CLI): riportato come informazione, mai come finding.
            'external_links_found' => count($result['external']),
            'cli_hint' => 'php artisan content:link-reachability-audit',
        ];
    }

    /** @return array<string, mixed> */
    private function mediaDomain(): array
    {
        $result = $this->mediaAudit->audit();
        $flagged = collect($result['rows'])->filter(fn (array $r) => $r['findings'] !== [])->values()->all();

        return [
            'label' => 'Media (alt/crediti/peso/formato/file mancanti)',
            'available' => true,
            'checked_count' => $result['analyzed'],
            'finding_count' => count($flagged),
            'flagged' => $flagged,
            'breakdown' => [
                'missing_alt' => $result['missing_alt'],
                'missing_credit' => $result['missing_credit'],
                'missing_file' => $result['missing_file'],
                'oversized' => $result['oversized'],
                'non_optimal_format' => $result['non_optimal_format'],
            ],
            'cli_hint' => 'php artisan media:health-audit',
        ];
    }

    /** @return array<string, mixed> */
    private function wcagDomain(): array
    {
        $pages = $this->wcagAudit->audit();
        $flagged = collect($pages)->filter(fn (array $p) => $p['findings'] !== [])->values()->all();

        return [
            'label' => 'Accessibilità WCAG statica',
            'available' => true,
            'checked_count' => collect($pages)->where('http_status', 200)->count(),
            'total_count' => count($pages),
            'finding_count' => count($flagged),
            'flagged' => $flagged,
            'cli_hint' => 'php artisan pages:wcag-audit',
        ];
    }

    /** @return array<string, mixed> */
    private function performanceDomain(): array
    {
        return [
            'label' => 'Performance (Core Web Vitals)',
            'available' => false,
            'reason' => 'La baseline (docs/PERFORMANCE_LAB_BASELINE.md) viene misurata con uno script Node in-browser (scripts/performance-lab.mjs), non un servizio PHP: nessun dato aggregato interno da riassumere qui.',
            'cli_hint' => 'node scripts/performance-lab.mjs',
        ];
    }

    /** @return array<string, mixed> */
    private function keyboardNavigationDomain(): array
    {
        return [
            'label' => 'Navigazione da tastiera (skip-link, focus visibile)',
            'available' => false,
            'reason' => 'Verificata da un test browser reale (tests/browser/keyboard-navigation.spec.js), non da un servizio PHP: richiede un browser, nessun dato aggregato interno da riassumere qui.',
            'cli_hint' => 'npx playwright test tests/browser/keyboard-navigation.spec.js',
        ];
    }
}
