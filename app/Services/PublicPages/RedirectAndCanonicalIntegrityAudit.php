<?php

namespace App\Services\PublicPages;

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\Category;
use App\Models\ContentCluster;

/**
 * Cantiere 23 (programma 100-cantieri Kairus). `App\Services\PublicPages\PublicPageSeoAudit`
 * (Cantiere 22) verifica UN solo esempio per ciascun tipo di pagina —
 * sufficiente per accorgersi che il TIPO di pagina è rotto, mai per
 * scoprire che un singolo articolo/categoria/percorso REALE tra i tanti
 * ha un problema. Questo servizio estende quel controllo a scala,
 * riusando lo stesso meccanismo di richiesta in-process
 * (`InProcessPageFetcher`), e verifica in più il meccanismo di redirect
 * esistente (`App\Models\ArticleSlugRedirect`), che finora non era mai
 * stato controllato oltre la sua stessa logica applicativa.
 *
 * Due controlli distinti, sola lettura, nessun effetto collaterale:
 *
 * 1. `auditRedirects()`: per ogni vecchio slug articolo con un redirect
 *    registrato, verifica che risolva in uno dei due soli esiti previsti
 *    dal contratto di `ArticleController::show()` — un 301 verso
 *    l'articolo corrente, la cui pagina di arrivo risponde 200 con un
 *    canonical che si auto-riferisce, oppure un 404 quando l'articolo di
 *    destinazione non è più pubblicato (comportamento corretto e
 *    documentato, MAI un finding). Qualunque altro esito (redirect senza
 *    Location, redirect verso una pagina che non risponde 200, canonical
 *    di arrivo incoerente) è un'incoerenza reale.
 *
 * 2. `auditCanonicalConsistency()`: per OGNI categoria raggiungibile
 *    (`Category::publicOptions()` — righe DB pubbliche più il fallback
 *    legacy solo-config, Cantiere 21) e OGNI percorso pubblicamente
 *    visibile, verifica lo stesso auto-riferimento del canonical già
 *    controllato da PublicPageSeoAudit ma su OGNI istanza, non solo un
 *    campione. Per gli articoli — potenzialmente molte centinaia in un
 *    sito maturo, ciascuno con un rendering non banale (correlati,
 *    navigazione percorso, ecc.) — il controllo è limitato ai più
 *    recenti (`$articleLimit`, di default MAX_ARTICLES_CHECKED_BY_DEFAULT):
 *    un'esecuzione senza parametri non deve mai rischiare di impiegare
 *    minuti scansionando l'intero archivio; un operatore che lo desideri
 *    esplicitamente può alzare il limite da riga di comando.
 */
class RedirectAndCanonicalIntegrityAudit
{
    private const MAX_ARTICLES_CHECKED_BY_DEFAULT = 50;

    public function __construct(private readonly InProcessPageFetcher $fetcher) {}

    /**
     * @return list<array{old_slug: string, article_id: int, http_status: int, findings: list<string>}>
     */
    public function auditRedirects(): array
    {
        return ArticleSlugRedirect::all()
            ->map(fn (ArticleSlugRedirect $redirect) => $this->auditRedirect($redirect))
            ->all();
    }

    /**
     * @return array{old_slug: string, article_id: int, http_status: int, findings: list<string>}
     */
    private function auditRedirect(ArticleSlugRedirect $redirect): array
    {
        $url = route('articolo', ['slug' => $redirect->old_slug]);
        $response = $this->fetcher->fetch($url);
        $status = $response->getStatusCode();

        $result = [
            'old_slug' => $redirect->old_slug,
            'article_id' => $redirect->article_id,
            'http_status' => $status,
            'findings' => [],
        ];

        $target = Article::published()->find($redirect->article_id);

        // Un vecchio slug il cui articolo non è più pubblicato risponde
        // correttamente 404 (ArticleController::show() non reindirizza
        // mai verso un articolo non pubblicamente visibile): stato
        // atteso e documentato, mai un finding — ma SOLO se l'articolo di
        // destinazione è davvero non più pubblicato: un 404 mentre
        // l'articolo è ancora pubblicato è una regressione reale.
        if ($status === 404) {
            if ($target !== null) {
                $result['findings'][] = "Il vecchio slug risponde 404, ma l'articolo #{$redirect->article_id} è ancora pubblicato (dovrebbe reindirizzare a {$target->slug}).";
            }

            return $result;
        }

        // Il contratto di ArticleController::show() emette sempre e solo
        // un redirect 301 (mai 302): un 302 indicherebbe una regressione
        // da redirect permanente a temporaneo.
        if ($status !== 301) {
            $result['findings'][] = "Stato HTTP inatteso per un vecchio slug con redirect registrato: {$status} (atteso 301 verso l'articolo corrente, o 404 se non più pubblicato).";

            return $result;
        }

        $location = $response->headers->get('Location');
        if ($location === null || $location === '') {
            $result['findings'][] = 'Redirect senza header Location.';

            return $result;
        }

        if ($target === null) {
            $result['findings'][] = "Redirect 301 verso {$location}, ma l'articolo #{$redirect->article_id} non è (più) pubblicato.";

            return $result;
        }

        $expectedTarget = route('articolo', ['slug' => $target->slug]);
        if (rtrim($location, '/') !== rtrim($expectedTarget, '/')) {
            $result['findings'][] = "Il redirect porta a {$location} invece che all'articolo corrente {$expectedTarget}.";

            return $result;
        }

        $targetResponse = $this->fetcher->fetch($location);
        $targetStatus = $targetResponse->getStatusCode();
        if ($targetStatus !== 200) {
            $result['findings'][] = "Il redirect porta a {$location}, che risponde con stato {$targetStatus} invece di 200.";

            return $result;
        }

        $canonical = $this->selfCanonicalMismatch($target->metaCanonicalUrl(), (string) $targetResponse->getContent());
        if ($canonical !== null) {
            $result['findings'][] = "Il canonical della destinazione del redirect non corrisponde all'URL di arrivo: {$canonical}.";
        }

        return $result;
    }

    /**
     * @return list<array{type: string, url: string, http_status: int, findings: list<string>}>
     */
    public function auditCanonicalConsistency(int $articleLimit = self::MAX_ARTICLES_CHECKED_BY_DEFAULT): array
    {
        $results = [];

        foreach (Article::published()->take($articleLimit)->get() as $article) {
            $results[] = $this->auditArticleUrl($article);
        }

        foreach (array_keys(Category::publicOptions()) as $slug) {
            $results[] = $this->auditSingleUrl('categoria', route('categoria', ['slug' => $slug]));
        }

        foreach (ContentCluster::query()->publiclyVisible()->get() as $cluster) {
            $results[] = $this->auditSingleUrl('percorso', route('percorsi.show', ['slug' => $cluster->slug]));
        }

        return $results;
    }

    /**
     * Come auditSingleUrl(), ma il canonical atteso per un articolo non è
     * sempre il proprio self-URL: Article::metaCanonicalUrl() restituisce
     * l'override esplicito `canonical_url` quando presente (campo reale,
     * fillable, già gestito da articolo.blade.php). Confrontare sempre col
     * self-URL produrrebbe un falso positivo per ogni articolo con un
     * canonical_url legittimamente diverso.
     *
     * @return array{type: string, url: string, http_status: int, findings: list<string>}
     */
    private function auditArticleUrl(Article $article): array
    {
        $url = route('articolo', ['slug' => $article->slug]);
        $response = $this->fetcher->fetch($url);
        $status = $response->getStatusCode();

        $result = ['type' => 'articolo', 'url' => $url, 'http_status' => $status, 'findings' => []];

        if ($status !== 200) {
            $result['findings'][] = "Stato HTTP inatteso: {$status} (atteso 200 per una pagina considerata pubblicamente raggiungibile).";

            return $result;
        }

        $mismatch = $this->selfCanonicalMismatch($article->metaCanonicalUrl(), (string) $response->getContent());
        if ($mismatch !== null) {
            $result['findings'][] = $mismatch;
        }

        return $result;
    }

    /**
     * @return array{type: string, url: string, http_status: int, findings: list<string>}
     */
    private function auditSingleUrl(string $type, string $url): array
    {
        $response = $this->fetcher->fetch($url);
        $status = $response->getStatusCode();

        $result = ['type' => $type, 'url' => $url, 'http_status' => $status, 'findings' => []];

        if ($status !== 200) {
            $result['findings'][] = "Stato HTTP inatteso: {$status} (atteso 200 per una pagina considerata pubblicamente raggiungibile).";

            return $result;
        }

        $mismatch = $this->selfCanonicalMismatch($url, (string) $response->getContent());
        if ($mismatch !== null) {
            $result['findings'][] = $mismatch;
        }

        return $result;
    }

    /**
     * @return string|null una descrizione del disallineamento, o null se il canonical si auto-riferisce correttamente
     */
    private function selfCanonicalMismatch(string $expectedUrl, string $html): ?string
    {
        $canonical = preg_match('#<link rel="canonical" href="(.*?)">#', $html, $matches) === 1 ? $matches[1] : null;

        if ($canonical === null) {
            return 'tag <link rel="canonical"> assente';
        }

        // Stessa tolleranza di PublicPageSeoAudit: la sola barra finale
        // non conta come mismatch (es. la home la normalizza
        // deliberatamente, vedi resources/views/home.blade.php).
        if (rtrim($canonical, '/') !== rtrim($expectedUrl, '/')) {
            return "atteso {$expectedUrl}, trovato {$canonical}";
        }

        return null;
    }
}
