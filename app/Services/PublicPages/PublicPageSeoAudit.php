<?php

namespace App\Services\PublicPages;

/**
 * Cantiere 22 (programma 100-cantieri Kairus). Prima di questo servizio,
 * ogni test di canonical/SEO/JSON-LD copriva UN singolo tipo di pagina
 * hardcoded (es. `ArchivePaginationCanonicalTest`, `HomeStructuredDataTest`,
 * `ArticleStructuredDataTest`) — nessuno iterava genericamente su ogni
 * tipo di pagina pubblica. `App\Services\PublicPages\PublicPageInventory`
 * (Cantiere 21) fornisce ora quel catalogo con un URL di esempio per
 * ciascun tipo: questo servizio lo consuma per verificare, pagina per
 * pagina, lo stesso insieme di fatti HTTP-level che prima si controllava
 * solo caso per caso.
 *
 * Sola lettura: ogni richiesta è un GET in-process tramite
 * `InProcessPageFetcher` (Cantiere 23: estratto qui da questa stessa
 * classe perché anche gli audit successivi che verificano più pagine
 * reali ne hanno bisogno, mai una sua duplicazione), non modifica mai
 * alcun contenuto.
 *
 * Verifiche eseguite per ogni pagina con un `sample_url` risolto (una
 * pagina dinamica senza alcun esempio pubblico — vedi
 * PublicPageInventory — è semplicemente saltata, `checked: false`, non
 * un fallimento):
 * - stato HTTP diverso da 200 (redirect/errore inatteso per una pagina
 *   che l'inventario considera pubblicamente raggiungibile ADESSO);
 * - tag `<title>` assente o vuoto;
 * - `<meta name="description">` assente o vuoto;
 * - `<link rel="canonical">` assente, oppure presente ma con un href
 *   diverso dall'URL effettivamente richiesto (mai un giudizio su QUALE
 *   canonical sia "corretto" oltre l'auto-riferimento, che è il
 *   contratto già documentato in resources/views/layouts/partials/head.blade.php
 *   e verificato altrove per le pagine paginate);
 * - dati strutturati JSON-LD assenti, SOLO per i tipi di pagina per cui
 *   esiste già un partial/blocco dedicato nel repository (home, categoria,
 *   articolo, percorso, percorsi index, autore — vedi
 *   EXPECTS_JSON_LD_KEYS) — mai imposto su pagine statiche/di utilità
 *   (ricerca, pagine legali, turing) che non sono mai state pensate per
 *   averne, per evitare rumore che nasconderebbe le lacune reali.
 *
 * Il tag `<meta name="robots">` è sempre riportato (il suo valore è
 * un'informazione, non un giudizio: una pagina noindex può essere una
 * scelta editoriale legittima), mai un finding basato sul suo contenuto.
 */
class PublicPageSeoAudit
{
    /**
     * @var list<string>
     */
    private const EXPECTS_JSON_LD_KEYS = ['home', 'categoria', 'articolo', 'percorso', 'percorsi_index', 'autore'];

    public function __construct(
        private readonly PublicPageInventory $inventory,
        private readonly InProcessPageFetcher $fetcher,
    ) {}

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     route_name: string,
     *     kind: 'static'|'dynamic',
     *     sample_url: string|null,
     *     checked: bool,
     *     http_status: int|null,
     *     title_present: bool|null,
     *     description_present: bool|null,
     *     canonical: string|null,
     *     robots: string|null,
     *     json_ld_blocks: int|null,
     *     findings: list<string>,
     * }>
     */
    public function audit(): array
    {
        return array_map(fn (array $page) => $this->auditPage($page), $this->inventory->pages());
    }

    /**
     * @param  array{key: string, label: string, route_name: string, kind: 'static'|'dynamic', sample_url: string|null}  $page
     */
    private function auditPage(array $page): array
    {
        $base = $page + [
            'checked' => false,
            'http_status' => null,
            'title_present' => null,
            'description_present' => null,
            'canonical' => null,
            'robots' => null,
            'json_ld_blocks' => null,
            'findings' => [],
        ];

        if ($page['sample_url'] === null) {
            return $base;
        }

        $response = $this->fetcher->fetch($page['sample_url']);
        $status = $response->getStatusCode();
        $base['checked'] = true;
        $base['http_status'] = $status;

        if ($status !== 200) {
            $base['findings'][] = "Stato HTTP inatteso: {$status} (atteso 200 per una pagina considerata pubblicamente raggiungibile).";

            return $base;
        }

        $html = (string) $response->getContent();
        $findings = [];

        $titlePresent = $this->matchesNonEmpty($html, '#<title>(.*?)</title>#s');
        $base['title_present'] = $titlePresent;
        if (! $titlePresent) {
            $findings[] = 'Tag <title> assente o vuoto.';
        }

        $descriptionPresent = $this->matchesNonEmpty($html, '#<meta name="description" content="(.*?)">#s');
        $base['description_present'] = $descriptionPresent;
        if (! $descriptionPresent) {
            $findings[] = 'Meta description assente o vuota.';
        }

        $canonical = $this->firstMatch($html, '#<link rel="canonical" href="(.*?)">#');
        $base['canonical'] = $canonical;
        if ($canonical === null) {
            $findings[] = 'Tag <link rel="canonical"> assente.';
        } elseif (rtrim($canonical, '/') !== rtrim($page['sample_url'], '/')) {
            // La sola barra finale non conta come mismatch: alcune pagine
            // (es. la home, vedi resources/views/home.blade.php) la
            // normalizzano deliberatamente nel proprio canonical, una
            // scelta SEO legittima e già in uso, non un bug da segnalare.
            $findings[] = "Canonical non corrisponde all'URL richiesto: {$canonical}.";
        }

        $base['robots'] = $this->firstMatch($html, '#<meta name="robots" content="(.*?)">#');

        $jsonLdCount = preg_match_all('#<script type="application/ld\+json">#', $html);
        $base['json_ld_blocks'] = $jsonLdCount;
        if ($jsonLdCount === 0 && in_array($page['key'], self::EXPECTS_JSON_LD_KEYS, true)) {
            $findings[] = 'Nessun blocco JSON-LD (dati strutturati) presente, atteso per questo tipo di pagina.';
        }

        $base['findings'] = $findings;

        return $base;
    }

    private function matchesNonEmpty(string $html, string $pattern): bool
    {
        return preg_match($pattern, $html, $matches) === 1 && trim($matches[1]) !== '';
    }

    private function firstMatch(string $html, string $pattern): ?string
    {
        return preg_match($pattern, $html, $matches) === 1 ? $matches[1] : null;
    }
}
