<?php

namespace App\Services\PublicPages;

use App\Models\AuditFindingStatus;
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
 * audit esistente, solo chiamare, raccogliere e riassumere.
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
 *
 * Cantiere 31: ogni riga segnalata nei sei domini reali riceve ora una
 * `severity` (HIGH/MEDIUM — stesso vocabolario a due livelli già usato da
 * EditorialOperationsDashboardService, mai una terza soglia inventata qui)
 * calcolata SOLO su campi strutturati già esposti dall'audit sottostante
 * (http_status, canonical, hits, ecc.) — mai una nuova regola di dominio,
 * solo una classificazione della severità di un finding già deciso
 * altrove — e una `finding_key` stabile, usata da AuditFindingStatusService
 * (persistenza, non qui) per il workflow "presa in carico"/"ignorato". Una
 * riga con stato "ignorato" resta visibile (trasparenza) ma esce dai
 * conteggi "aperti" della dashboard, stesso principio già in uso da
 * SearchConsoleOpportunityProvider per search_opportunity_statuses.
 */
class PublicHealthDashboardService
{
    private const ARTICLE_LIMIT = 50;

    private const NOT_FOUND_DISPLAY_LIMIT = 50;

    /**
     * Limite ampio ma non illimitato per i conteggi aperti/ignorati del
     * registro 404 (Codex, PR #579, P2) — una query singola resta
     * economica anche a questa scala, ben oltre quanti path 404 distinti
     * un registro di questa natura accumula realisticamente prima che un
     * editore li smaltisca.
     */
    private const NOT_FOUND_COUNTING_LIMIT = 5000;

    /**
     * Soglia oltre la quale un path 404 realmente visitato passa da
     * MEDIUM a HIGH — arbitraria ma dichiarata: un path colpito da molti
     * visitatori reali merita priorità su uno visitato una volta sola
     * (probabile refuso isolato).
     */
    private const NOT_FOUND_HIGH_SEVERITY_HIT_THRESHOLD = 10;

    /**
     * I sei domini con un audit PHP reale (a differenza di 'performance'
     * e 'keyboard_navigation') — pubblico perche' il controller lo usa
     * per validare il dominio ricevuto da updateFindingStatus(), mai
     * duplicato come lista letterale altrove.
     */
    public const REAL_DOMAIN_KEYS = ['seo', 'redirects_canonical', 'not_found', 'links', 'media', 'wcag'];

    public function __construct(
        private readonly PublicPageSeoAudit $seoAudit,
        private readonly RedirectAndCanonicalIntegrityAudit $redirectCanonicalAudit,
        private readonly NotFoundHitTracker $notFoundHitTracker,
        private readonly LinkReachabilityAuditService $linkAudit,
        private readonly MediaLibraryHealthAudit $mediaAudit,
        private readonly WcagInternalAudit $wcagAudit,
        private readonly AuditFindingStatusService $findingStatuses,
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

            $domains = $this->attachStatuses($domains);

            $openFindingsTotal = collect($domains)->only(self::REAL_DOMAIN_KEYS)->sum('open_count');
            $dismissedFindingsTotal = collect($domains)->only(self::REAL_DOMAIN_KEYS)->sum('dismissed_count');
            $highSeverityOpenTotal = collect($domains)->only(self::REAL_DOMAIN_KEYS)->sum('high_open_count');

            return [
                'status' => $openFindingsTotal === 0 ? 'SANA' : 'DA_RIVEDERE',
                'open_findings_total' => $openFindingsTotal,
                'dismissed_findings_total' => $dismissedFindingsTotal,
                'high_severity_open_total' => $highSeverityOpenTotal,
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

    /**
     * Cantiere 31: una sola query per l'intero snapshot (mai una per
     * riga), stesso principio di SearchOpportunityStatusService::statusesFor().
     * Una riga senza stato persistito è implicitamente "new" — nessuna
     * riga viene mai scritta solo per essere letta.
     *
     * Codex (PR #579, P2): i conteggi aperti/ignorati/gravità alta devono
     * riflettere l'INTERO insieme di righe di un dominio, non solo la
     * porzione mostrata in tabella — altrimenti ignorare un finding fuori
     * dalla porzione mostrata (es. un path 404 oltre i 50 più frequenti)
     * non riduce mai il conteggio "aperti", e la dashboard può restare
     * "DA_RIVEDERE" (o peggio, apparire "SANA") in modo scorrelato dallo
     * stato reale. Un dominio può opzionalmente fornire `_counting_rows`
     * (l'insieme completo, usato SOLO qui) quando è più ampio di `flagged`
     * (la sola porzione mostrata) — rimosso dallo snapshot finale.
     *
     * @param  array<string, array<string, mixed>>  $domains
     * @return array<string, array<string, mixed>>
     */
    private function attachStatuses(array $domains): array
    {
        $countingRowsByDomain = collect(self::REAL_DOMAIN_KEYS)
            ->mapWithKeys(fn (string $key) => [$key => $domains[$key]['_counting_rows'] ?? $domains[$key]['flagged']]);

        $findingKeys = $countingRowsByDomain
            ->flatMap(fn (array $rows) => collect($rows)->pluck('finding_key'))
            ->values()
            ->all();

        $statuses = $this->findingStatuses->statusesFor($findingKeys);

        foreach (self::REAL_DOMAIN_KEYS as $key) {
            $countingRows = collect($countingRowsByDomain[$key])
                ->map(function (array $row) use ($statuses) {
                    $row['status'] = $statuses[$row['finding_key']] ?? AuditFindingStatus::STATUS_NEW;

                    return $row;
                });

            $open = $countingRows->where('status', '!=', AuditFindingStatus::STATUS_DISMISSED);

            if (isset($domains[$key]['_counting_rows'])) {
                // 'flagged' è già un sotto-insieme (stesso finding_key) di
                // counting_rows: riusa lo stato già calcolato sopra invece
                // di interrogare di nuovo il database per le stesse chiavi.
                $displayedKeys = collect($domains[$key]['flagged'])->pluck('finding_key');
                $domains[$key]['flagged'] = $countingRows->whereIn('finding_key', $displayedKeys)->values()->all();
                unset($domains[$key]['_counting_rows']);
            } else {
                $domains[$key]['flagged'] = $countingRows->values()->all();
            }

            $domains[$key]['open_count'] = $open->count();
            $domains[$key]['dismissed_count'] = $countingRows->count() - $open->count();
            $domains[$key]['high_open_count'] = $open->where('severity', 'HIGH')->count();
        }

        return $domains;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withMeta(array $rows, string $domain, Closure $identifier, Closure $severity): array
    {
        return collect($rows)
            ->map(function (array $row) use ($domain, $identifier, $severity) {
                $row['finding_key'] = $domain.'|'.$identifier($row);
                $row['severity'] = $severity($row);

                return $row;
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function seoDomain(): array
    {
        $pages = $this->seoAudit->audit();
        $flagged = collect($pages)->filter(fn (array $p) => $p['findings'] !== [])->values()->all();
        $flagged = $this->withMeta(
            $flagged,
            'seo',
            fn (array $r) => $r['key'],
            // Codex (PR #579, P1): PublicPageSeoAudit espone 'sample_url',
            // mai 'url' — un accesso alla chiave sbagliata qui non veniva
            // mai eseguito nei test perche' http_status !== 200 va sempre
            // in cortocircuito prima, ma su una pagina reale che risponde
            // 200 con un canonical incoerente questo confronto viene
            // eseguito davvero e avrebbe fatto fallire l'intera dashboard.
            fn (array $r) => ($r['http_status'] !== 200
                || $r['canonical'] === null
                || rtrim((string) $r['canonical'], '/') !== rtrim((string) $r['sample_url'], '/')
            ) ? 'HIGH' : 'MEDIUM',
        );

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

        $flaggedRedirects = collect($redirects)
            ->filter(fn (array $r) => $r['findings'] !== [])
            ->map(fn (array $r) => [...$r, 'kind' => 'redirect'])
            ->values()
            ->all();
        $flaggedCanonicals = collect($canonicals)
            ->filter(fn (array $c) => $c['findings'] !== [])
            ->map(fn (array $c) => [...$c, 'kind' => 'canonical'])
            ->values()
            ->all();

        // Un'unica lista, come ogni altro dominio: "Tipo" (kind) distingue
        // le due righe già nella stessa tabella della vista, mai due
        // strutture parallele da trattare diversamente qui.
        $flagged = $this->withMeta(
            [...$flaggedRedirects, ...$flaggedCanonicals],
            'redirects_canonical',
            fn (array $r) => $r['kind'] === 'redirect'
                ? 'redirect:'.$r['old_slug']
                : 'canonical:'.$r['type'].':'.$r['url'],
            // Un vecchio slug che non redireziona correttamente e' sempre
            // un link rotto reale per chi arriva da un bookmark/backlink
            // (HIGH); un canonical incoerente ma su una pagina che
            // comunque risponde 200 e' un problema SEO, non di
            // navigabilita' (MEDIUM).
            fn (array $r) => $r['kind'] === 'redirect' || $r['http_status'] !== 200 ? 'HIGH' : 'MEDIUM',
        );

        return [
            'label' => 'Redirect vecchi slug e coerenza canonical',
            'available' => true,
            'checked_count' => count($redirects) + count($canonicals),
            'finding_count' => count($flagged),
            'flagged' => $flagged,
            'cli_hint' => 'php artisan pages:redirect-canonical-audit',
        ];
    }

    /** @return array<string, mixed> */
    private function notFoundDomain(): array
    {
        // Codex (PR #579, P2): calcolare severità/stato solo sui path
        // mostrati in tabella (NOT_FOUND_DISPLAY_LIMIT) sotto-contava i
        // finding "aperti" quando il registro supera quel limite — un
        // path 404 oltre i 50 più frequenti mostrati non contribuiva mai
        // ai conteggi, quindi ignorarne uno tra i 50 mostrati poteva far
        // apparire la dashboard "SANA" con altri 404 aperti oltre quel
        // limite. _counting_rows copre l'intero registro (limite ampio
        // ma non illimitato: oltre questa soglia i conteggi tornano ad
        // essere un'approssimazione, non più un problema pratico per un
        // registro di questa natura); 'flagged' resta limitato ai path
        // più frequenti per la sola tabella.
        $allRows = $this->withMeta(
            collect($this->notFoundHitTracker->topHits(self::NOT_FOUND_COUNTING_LIMIT))
                ->map(fn (NotFoundHit $hit) => [
                    'path' => $hit->path,
                    'hits' => $hit->hits,
                    'last_seen_at' => $hit->last_seen_at,
                    'findings' => ["Visitato {$hit->hits} volte dal traffico reale."],
                ])
                ->all(),
            'not_found',
            fn (array $r) => $r['path'],
            fn (array $r) => $r['hits'] >= self::NOT_FOUND_HIGH_SEVERITY_HIT_THRESHOLD ? 'HIGH' : 'MEDIUM',
        );

        return [
            'label' => 'Registro 404 dal traffico reale',
            'available' => true,
            'finding_count' => NotFoundHit::query()->count(),
            'flagged' => array_slice($allRows, 0, self::NOT_FOUND_DISPLAY_LIMIT),
            '_counting_rows' => $allRows,
            'cli_hint' => 'php artisan pages:not-found-registry',
        ];
    }

    /** @return array<string, mixed> */
    private function linksDomain(): array
    {
        $result = $this->linkAudit->audit(self::ARTICLE_LIMIT, checkExternal: false);
        $flagged = collect($result['internal'])->filter(fn (array $r) => $r['findings'] !== [])->values()->all();
        $flagged = $this->withMeta(
            $flagged,
            'links',
            fn (array $r) => $r['url'],
            // Un collegamento interno rotto e' sempre un link morto reale
            // per chi lo clicca — stesso trattamento di un redirect rotto.
            fn (array $r) => 'HIGH',
        );

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
        $flagged = $this->withMeta(
            $flagged,
            'media',
            fn (array $r) => (string) $r['id'],
            // Un file registrato ma assente su disco e' una rottura reale
            // (immagine spezzata per il lettore); alt/credito/peso/formato
            // sono lacune di qualita', non di disponibilita'.
            fn (array $r) => in_array(
                'File assente su disco (registrato in Libreria media, ma non trovato in public/assets/img).',
                $r['findings'],
                true,
            ) ? 'HIGH' : 'MEDIUM',
        );

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
        $flagged = $this->withMeta(
            $flagged,
            'wcag',
            fn (array $r) => $r['key'],
            // Skip-link/landmark/lang assenti compromettono la
            // navigazione stessa per chi usa tastiera/screen reader
            // (HIGH); struttura heading, alt e nome accessibile sono
            // lacune di qualita' (MEDIUM) — stessi messaggi stabili gia'
            // testati in WcagInternalAuditTest.
            fn (array $r) => collect($r['findings'])->contains(
                fn (string $f) => str_contains($f, 'Skip-link')
                    || str_starts_with($f, 'Landmark')
                    || str_contains($f, 'Attributo lang assente'),
            ) ? 'HIGH' : 'MEDIUM',
        );

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
