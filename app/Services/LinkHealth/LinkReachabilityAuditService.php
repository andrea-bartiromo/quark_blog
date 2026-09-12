<?php

namespace App\Services\LinkHealth;

use App\Models\Article;
use App\Services\PublicPages\InProcessPageFetcher;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cantiere 25 (programma 100-cantieri Kairus). Ispezione preliminare:
 * App\Services\InternalLinking\InternalLinkAuditService (content:internal-link-audit)
 * classifica già i collegamenti /articolo/{slug} tra articoli
 * (compresi quelli rotti, classificazione 'missing') — nessuna
 * duplicazione qui. Resta scoperto ogni ALTRO tipo di collegamento nel
 * corpo di un articolo: verso categoria/percorso/pagine statiche
 * (interno, ma mai verificato per raggiungibilità reale — a differenza
 * degli /articolo/, che sono classificati contro lo stato noto del
 * database, non con una richiesta HTTP) e verso siti esterni (mai
 * tracciati da nessun audit esistente). Dipende dal Cantiere 21
 * (nozione di "pagina pubblica raggiungibile" — Category::publicOptions(),
 * ContentCluster::publiclyVisible()) per sapere quali destinazioni
 * interne sono legittime a prescindere dal risultato della richiesta.
 *
 * Due controlli distinti, sola lettura:
 *
 * 1. Collegamenti interni (diversi da /articolo/, già coperti):
 *    verificati con lo stesso GET in-process di InProcessPageFetcher
 *    (Cantiere 22/23) — nessuna vera chiamata di rete, nessun rischio
 *    di flakiness in CI. Un 301 (redirect) è accettato come
 *    raggiungibile (es. un vecchio slug categoria/percorso, se mai
 *    esistesse un meccanismo di redirect equivalente futuro); solo un
 *    4xx/5xx e' un finding.
 *
 * 2. Collegamenti esterni: una VERA richiesta HTTP in uscita (stesso
 *    pattern già usato in produzione da
 *    App\Services\ProjectTaskGithubSyncService per l'API GitHub) — mai
 *    eseguita di default (vedi $checkExternal), perché lenta e
 *    intrinsecamente non deterministica (il sito di terzi potrebbe
 *    essere temporaneamente giù, bloccare l'IP del server, ecc.): un
 *    operatore la richiede esplicitamente da riga di comando quando
 *    vuole un controllo reale, mai come parte di un'esecuzione
 *    automatica o di un test. Qualunque eccezione di rete (timeout,
 *    DNS, connessione rifiutata) è trattata come "irraggiungibile", mai
 *    rilanciata: un sito di terzi lento o giù non deve mai far fallire
 *    l'audit stesso.
 */
class LinkReachabilityAuditService
{
    private const MAX_ARTICLES_CHECKED_BY_DEFAULT = 50;

    private const EXTERNAL_REQUEST_TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly ArticleLinkExtractor $extractor,
        private readonly InProcessPageFetcher $fetcher,
    ) {}

    /**
     * @return array{internal: list<array{url: string, articles: list<string>, http_status: ?int, findings: list<string>}>, external: list<array{url: string, articles: list<string>, reachable: ?bool, findings: list<string>}>}
     */
    public function audit(int $articleLimit = self::MAX_ARTICLES_CHECKED_BY_DEFAULT, bool $checkExternal = false): array
    {
        $internalBySlug = [];
        $externalByUrl = [];

        foreach (Article::published()->latest('published_at')->take($articleLimit)->get() as $article) {
            foreach ($this->extractor->extract((string) $article->body) as $link) {
                if ($this->isAlreadyCoveredArticleLink($link['url'])) {
                    continue;
                }

                if ($link['type'] === 'internal') {
                    $internalBySlug[$link['url']]['articles'][$article->slug] = true;
                } else {
                    $externalByUrl[$link['url']]['articles'][$article->slug] = true;
                }
            }
        }

        return [
            'internal' => $this->auditInternal($internalBySlug),
            'external' => $checkExternal ? $this->auditExternal($externalByUrl) : $this->skippedExternal($externalByUrl),
        ];
    }

    /**
     * App\Services\InternalLinking\InternalLinkAuditService già copre
     * ogni /articolo/{slug} (compresi i rotti, classificazione
     * 'missing') con la sua stessa logica di risoluzione redirect —
     * riprodurla qui con una semplice richiesta HTTP darebbe un
     * risultato meno accurato (non conoscerebbe i redirect di slug) e
     * duplicherebbe un controllo già esistente.
     */
    private function isAlreadyCoveredArticleLink(string $url): bool
    {
        return preg_match('~(?:^|/)articolo/[^/?#]+~', $url) === 1;
    }

    /**
     * @param  array<string, array{articles: array<string, true>}>  $internalBySlug
     * @return list<array{url: string, articles: list<string>, http_status: ?int, findings: list<string>}>
     */
    private function auditInternal(array $internalBySlug): array
    {
        $results = [];

        foreach ($internalBySlug as $url => $data) {
            $path = $this->extractor->toInternalPath($url);
            $status = null;
            $findings = [];

            try {
                $status = $this->fetcher->fetch($path)->getStatusCode();

                if (! in_array($status, [200, 301], true)) {
                    $findings[] = "Stato HTTP inatteso: {$status} (atteso 200, o 301 per un redirect).";
                }
            } catch (Throwable $e) {
                $findings[] = "Errore durante la verifica: {$e->getMessage()}";
            }

            $results[] = [
                'url' => $url,
                'articles' => array_keys($data['articles']),
                'http_status' => $status,
                'findings' => $findings,
            ];
        }

        return $results;
    }

    /**
     * @param  array<string, array{articles: array<string, true>}>  $externalByUrl
     * @return list<array{url: string, articles: list<string>, reachable: ?bool, findings: list<string>}>
     */
    private function auditExternal(array $externalByUrl): array
    {
        $results = [];

        foreach ($externalByUrl as $url => $data) {
            [$reachable, $findings] = $this->checkExternalUrl($url);

            $results[] = [
                'url' => $url,
                'articles' => array_keys($data['articles']),
                'reachable' => $reachable,
                'findings' => $findings,
            ];
        }

        return $results;
    }

    /**
     * @return array{0: ?bool, 1: list<string>}
     */
    private function checkExternalUrl(string $url): array
    {
        try {
            $response = Http::timeout(self::EXTERNAL_REQUEST_TIMEOUT_SECONDS)
                ->withUserAgent('KairusLinkHealthAudit/1.0 (+https://kairus.it)')
                ->head($url);

            // Alcuni server rifiutano HEAD (405/501): un GET è il
            // fallback naturale prima di concludere che il link non
            // risponde, non un errore da segnalare a sua volta.
            if (in_array($response->status(), [405, 501], true)) {
                $response = Http::timeout(self::EXTERNAL_REQUEST_TIMEOUT_SECONDS)
                    ->withUserAgent('KairusLinkHealthAudit/1.0 (+https://kairus.it)')
                    ->get($url);
            }

            if ($response->successful() || $response->redirect()) {
                return [true, []];
            }

            return [false, ["Il sito esterno risponde con stato {$response->status()}."]];
        } catch (Throwable $e) {
            return [false, ["Il sito esterno non è raggiungibile: {$e->getMessage()}"]];
        }
    }

    /**
     * @param  array<string, array{articles: array<string, true>}>  $externalByUrl
     * @return list<array{url: string, articles: list<string>, reachable: null, findings: list<string>}>
     */
    private function skippedExternal(array $externalByUrl): array
    {
        return array_map(
            fn (array $data, string $url) => [
                'url' => $url,
                'articles' => array_keys($data['articles']),
                'reachable' => null,
                'findings' => [],
            ],
            $externalByUrl,
            array_keys($externalByUrl)
        );
    }
}
