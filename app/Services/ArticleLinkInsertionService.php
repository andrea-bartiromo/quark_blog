<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Inserisce UN link interno nel body di un articolo, in modo sicuro, su
 * richiesta esplicita della redazione (azione "Inserisci" — mai
 * automatico). Non salva nulla: riceve l'HTML corrente (anche non ancora
 * salvato lato client) e restituisce il nuovo HTML, che resta nel form
 * finché non è la redazione stessa a premere "Salva modifiche".
 *
 * Sicurezza: costruisce il nuovo <a> esclusivamente tramite API DOM
 * (createElement/setAttribute/createTextNode), mai concatenazione di
 * stringhe. Questo però NON basta da solo: l'escaping degli attributi in
 * fase di serializzazione HTML è comportamento del motore libxml2
 * sottostante, che si è verificato differire tra build (su alcune build,
 * un apice doppio nel valore di un attributo href/src viene percent-encoded
 * durante la normalizzazione dell'URI; su altre potrebbe non esserlo). Per
 * questo l'href non viene mai passato a setAttribute() senza prima essere
 * validato da isSafeInternalHref(): un valore che contenga apici, angolari
 * o uno schema diverso da http/https sul proprio host viene rifiutato a
 * monte — l'inserimento fallisce esplicitamente (torna null) invece di
 * confidare nell'escaping del serializzatore per neutralizzarlo (FASE 9).
 *
 * Regole di inserimento (FASE 4):
 *   - mai dentro un link esistente (<a>);
 *   - mai dentro un titolo (<h1>-<h6>) o una citazione (<blockquote>);
 *   - l'anchor deve essere trovata testualmente, invariata, in un singolo
 *     nodo di testo del body corrente — se il testo è cambiato da quando è
 *     stato generato il suggerimento (o la frase è spezzata da markup
 *     inline tra il momento del suggerimento e quello dell'inserimento),
 *     l'inserimento fallisce esplicitamente (torna null) invece di
 *     inserire in un punto diverso o approssimato.
 */
class ArticleLinkInsertionService
{
    private const FORBIDDEN_ANCESTORS = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'];

    private const SKIPPED_SUBTREES = ['script', 'style'];

    /**
     * @return string|null il nuovo HTML del body, o null se l'anchor non è
     *                     stata trovata in una posizione valida, o l'href
     *                     non supera la validazione di sicurezza (nessuna
     *                     modifica in entrambi i casi).
     */
    public function insert(string $bodyHtml, string $anchorText, string $targetUrl): ?string
    {
        if (! $this->isSafeInternalHref($targetUrl)) {
            return null;
        }

        $located = $this->locateInsertionPoint($bodyHtml, $anchorText);

        if ($located === null) {
            return null;
        }

        $this->splitAndWrap($located['dom'], $located['textNode'], $located['position'], trim($anchorText), $targetUrl);

        return $this->innerHtml($located['dom'], $located['root']);
    }

    /**
     * Contratto esplicito sull'href: anche se oggi ogni chiamante passa
     * solo URL generati internamente da route('articolo', $slug), l'href
     * viene comunque validato qui — mai concatenato/escapato "a fidarsi"
     * di chi chiama né della serializzazione DOM (vedi docblock di classe).
     *
     * Ammesso solo:
     *   - un percorso relativo interno che inizia con "/" (mai "//host/..."
     *     protocol-relative, che punterebbe a un altro dominio);
     *   - un URL assoluto con schema http/https il cui host coincide con
     *     quello configurato in app.url (route() genera URL assoluti).
     * Rifiutato sempre: qualunque valore contenga apici, angolari, backtick
     * o spazi (non possono mai comparire in un URL interno legittimo, e
     * sono esattamente i caratteri che potrebbero rompere un attributo
     * HTML se la serializzazione non li escapasse), e qualunque schema
     * diverso da http/https (javascript:, data:, vbscript:, ecc.).
     */
    private function isSafeInternalHref(string $url): bool
    {
        // Il backslash è rifiutato insieme ad apici/angolari/spazi, non solo
        // per coerenza con "nessun mix \ e /": nei parser URL WHATWG usati
        // dai browser reali (a differenza di PHP parse_url()), "\" dentro la
        // parte authority di uno schema "speciale" (http/https) si comporta
        // come "/" — un valore tipo "https://evil.example\@<app-host>/x"
        // supera il controllo host qui sotto (PHP legge host=<app-host>,
        // trattando "evil.example\" come userinfo) ma un browser può
        // risolverlo diversamente. Rifiutarlo a monte evita di dover
        // replicare esattamente la semantica di parsing di un browser.
        if ($url === '' || preg_match('/["\'<>`\s\\\\]/u', $url) === 1) {
            return false;
        }

        if (preg_match('#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $url, $schemeMatch) === 1) {
            if (! in_array(mb_strtolower($schemeMatch[1]), ['http', 'https'], true)) {
                return false;
            }

            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $urlHost = parse_url($url, PHP_URL_HOST);

            return $appHost !== null && $urlHost !== null && mb_strtolower($urlHost) === mb_strtolower($appHost);
        }

        return str_starts_with($url, '/') && ! str_starts_with($url, '//');
    }

    /**
     * True se $anchorText è presente per intero in un singolo nodo di
     * testo idoneo del body — senza modificare nulla. Usata da
     * App\Services\ArticleLinkSuggestionService per scartare in fase di
     * generazione le anchor che attraverserebbero un tag inline (es.
     * <strong>) o che si trovano solo dentro un link/titolo/citazione
     * esistente: una anchor non verificata qui fallirebbe comunque
     * silenziosamente al momento di "Inserisci".
     */
    public function canInsert(string $bodyHtml, string $anchorText): bool
    {
        return $this->locateInsertionPoint($bodyHtml, $anchorText) !== null;
    }

    /**
     * Conta i collegamenti interni realmente presenti nel body — non solo
     * quelli passati dal suggeritore (App\Models\ArticleLinkSuggestion
     * traccia solo i propri suggerimenti, mai un link digitato a mano).
     * Riusa isSafeInternalHref() per la definizione di "interno", la
     * stessa già applicata prima di ogni inserimento — nessuna seconda
     * definizione divergente. Un href assente o non valido/esterno non
     * viene conteggiato. HTML malformato non solleva mai un'eccezione:
     * stesso parsing con soppressione errori libxml già usato altrove in
     * questa classe.
     */
    public function countInternalLinks(string $bodyHtml): int
    {
        if (trim($bodyHtml) === '') {
            return 0;
        }

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__link_count_root__">'.$bodyHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@href]');

        if ($anchors === false) {
            return 0;
        }

        $count = 0;

        foreach ($anchors as $anchor) {
            if ($this->isSafeInternalHref($anchor->getAttribute('href'))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Slug degli ALTRI ARTICOLI Kairus collegati nel body (href verso
     * /articolo/{slug}, relativo o assoluto sullo stesso dominio) —
     * DEFINIZIONE UNICA di "collegamento ad articolo", condivisa da
     * App\Services\ArticleLinkSuggestionService (per non riproporre un
     * collegamento già presente) e dal badge "collegamenti ad articoli"
     * nella lista Admin Articoli (vedi countArticleLinks()). Prima
     * dell'estrazione qui, questa logica viveva duplicata implicitamente:
     * il badge contava QUALUNQUE link interno (homepage, categorie,
     * pagine statiche inclusi — vedi countInternalLinks()), mentre solo
     * il suggeritore aveva questa definizione più stretta.
     *
     * Deduplica per costruzione (array_unique): rappresenta gli ARTICOLI
     * DISTINTI raggiunti dal testo, non il numero grezzo di tag <a> — un
     * articolo collegato tre volte nello stesso paragrafo conta come 1.
     * Scelta deliberata, non solo per riuso di codice: la domanda
     * editoriale a cui il badge risponde ("ho già collegato altri
     * articoli Kairus?") riguarda QUALI e QUANTI articoli sono
     * raggiungibili, non quante volte compare il singolo link — due
     * link allo stesso articolo non sono "due collegamenti" nel senso
     * in cui un redattore la userebbe prima di pubblicare.
     *
     * Non richiede isSafeInternalHref(): un href assoluto verso un altro
     * dominio che contenesse per coincidenza il segmento "/articolo/xyz"
     * verrebbe qui riconosciuto (limite pre-esistente, noto, di probabilità
     * trascurabile — "/articolo/" è una convenzione di URL specifica di
     * Kairus).
     *
     * @return array<int, string>
     */
    public function linkedArticleSlugsInBody(string $html): array
    {
        if (trim($html) === '' || strip_tags($html) === $html) {
            return [];
        }

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $slugs = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            /** @var DOMElement $anchor */
            $href = $anchor->getAttribute('href');

            if ($href !== '' && preg_match('~/articolo/([^/?#]+)~', $href, $m)) {
                $slugs[] = $m[1];
            }
        }

        return array_unique($slugs);
    }

    /**
     * Badge "collegamenti ad articoli" (lista Admin Articoli) — conta gli
     * ALTRI ARTICOLI Kairus distinti raggiunti dal body, non tutti i link
     * interni generici (quello resta countInternalLinks(), usato altrove
     * per il badge sui soli articoli programmati). Nessuna query: il body
     * è già caricato dalla query principale della lista, stesso parsing
     * DOM di countInternalLinks(), nessun costo per riga aggiuntivo oltre
     * al parsing stesso.
     */
    public function countArticleLinks(string $bodyHtml): int
    {
        return count($this->linkedArticleSlugsInBody($bodyHtml));
    }

    /**
     * Internal Linking V2 (content:internal-link-audit) — come
     * linkedArticleSlugsInBody(), ma OGNI occorrenza (non deduplicata) con
     * il testo dell'anchor: l'audit ha bisogno di sapere se la STESSA
     * frase-anchor punta a destinazioni diverse nello stesso articolo
     * ("anchor ambigui" — FASE 16), cosa che l'elenco deduplicato di slug
     * non può più esprimere. Stessa definizione di "collegamento ad
     * articolo" di linkedArticleSlugsInBody() (stesso pattern
     * /articolo/{slug}), qui deliberatamente NON deduplicata: la
     * deduplicazione è responsabilità di chi consuma il risultato, in base
     * a cosa gli serve (il badge vuole "quanti articoli distinti", l'audit
     * vuole anche "quante volte e con quale testo").
     *
     * @return array<int, array{slug: string, anchorText: string}>
     */
    public function internalArticleLinkOccurrences(string $html): array
    {
        if (trim($html) === '' || strip_tags($html) === $html) {
            return [];
        }

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $occurrences = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            /** @var DOMElement $anchor */
            $href = $anchor->getAttribute('href');

            if ($href !== '' && preg_match('~/articolo/([^/?#]+)~', $href, $m)) {
                $occurrences[] = ['slug' => $m[1], 'anchorText' => trim($anchor->textContent)];
            }
        }

        return $occurrences;
    }

    /**
     * Rimuove (unwrap, mantenendo il testo dell'anchor) ogni link interno
     * presente nel body verso uno qualunque degli slug in $slugs. Usata da
     * App\Services\ArticleLinkSuggestionService::markAccepted() quando un
     * collegamento già inserito nel testo (via "Inserisci", in una
     * richiesta precedente) risulta non più temporalmente sicuro nel
     * momento in cui l'articolo viene davvero salvato — es. la
     * programmazione della source è stata spostata, nella stessa modifica,
     * a una data che rende il target non più sicuro (Codex, PR #165, P1
     * round 2). Accetta più slug, non solo quello attuale del target
     * (Codex, PR #165, P2 round 3): se il target è stato rinominato tra
     * "Inserisci" e il salvataggio, l'href già inviato dal client punta
     * ancora al VECCHIO slug — cercare solo lo slug corrente lascerebbe il
     * link non sicuro nel testo. Il chiamante passa lo slug corrente più
     * ogni vecchio slug noto (App\Models\ArticleSlugRedirect). Restituisce
     * l'HTML invariato se nessuno slug compare come href di alcun link:
     * nessuna modifica silenziosa "a vuoto".
     *
     * A differenza di linkedArticleSlugsInBody()/internalArticleLinkOccurrences()
     * (sola lettura, usate per conteggi/audit — un falso positivo lì è
     * innocuo), qui l'href viene anche validato con isSafeInternalHref()
     * PRIMA del match sullo slug (Codex, PR #165, P2 round 7): questo
     * metodo MODIFICA il body, quindi un link ESTERNO il cui path
     * contenesse per coincidenza "/articolo/{slug}" (es.
     * https://esempio.com/articolo/foo) non deve mai essere scambiato per
     * il collegamento interno Kairus e rimosso — sarebbe una perdita di
     * contenuto legittimo, non solo un'imprecisione di conteggio.
     *
     * @param  array<int, string>  $slugs
     */
    public function removeLinksToSlugs(string $bodyHtml, array $slugs): string
    {
        if ($slugs === [] || trim($bodyHtml) === '' || strip_tags($bodyHtml) === $bodyHtml) {
            return $bodyHtml;
        }

        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__link_remove_root__">'.$bodyHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//div[@id="__link_remove_root__"]')->item(0);

        if ($root === null) {
            return $bodyHtml;
        }

        $changed = false;

        foreach (iterator_to_array($dom->getElementsByTagName('a')) as $anchor) {
            /** @var DOMElement $anchor */
            $href = $anchor->getAttribute('href');

            $path = $href === '' ? null : parse_url($href, PHP_URL_PATH);

            // Codex, PR #165, P2 round 8: il path va estratto ed
            // ancorato all'INTERA rotta /articolo/{slug} — non basta che
            // "/articolo/{slug}" compaia da qualche parte nell'URL. Un
            // link interno legittimo verso un'altra pagina che contiene
            // per coincidenza quella sottostringa in query string (es.
            // "/ricerca?q=/articolo/foo") supererebbe isSafeInternalHref()
            // (stesso host) e un regex non ancorato lo scambierebbe per il
            // collegamento Kairus da rimuovere.
            if ($href === ''
                || ! $this->isSafeInternalHref($href)
                || $path === null
                || ! preg_match('~^/articolo/([^/]+)/?$~', $path, $m)
                || ! in_array($m[1], $slugs, true)
            ) {
                continue;
            }

            $parent = $anchor->parentNode;

            if ($parent === null) {
                continue;
            }

            foreach (iterator_to_array($anchor->childNodes) as $child) {
                $parent->insertBefore($child, $anchor);
            }

            $parent->removeChild($anchor);
            $changed = true;
        }

        if (! $changed) {
            return $bodyHtml;
        }

        return $this->innerHtml($dom, $root);
    }

    /**
     * @return array{dom: DOMDocument, root: DOMNode, textNode: DOMText, position: int}|null
     */
    private function locateInsertionPoint(string $bodyHtml, string $anchorText): ?array
    {
        $anchorText = trim($anchorText);

        if ($anchorText === '' || trim($bodyHtml) === '') {
            return null;
        }

        // libxml_use_internal_errors() muta uno stato globale al processo:
        // va ripristinato al valore precedente, non lasciato sempre a
        // true, altrimenti qualunque altro codice nella stessa richiesta
        // (o nello stesso worker, sotto coda/Octane) eredita silenziosamente
        // la soppressione degli errori libxml.
        $previousLibxmlState = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__link_insert_root__">'.$bodyHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlState);

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//div[@id="__link_insert_root__"]')->item(0);

        if ($root === null) {
            return null;
        }

        $target = $this->findInsertionPoint($root, $anchorText);

        if ($target === null) {
            return null;
        }

        [$textNode, $position] = $target;

        return ['dom' => $dom, 'root' => $root, 'textNode' => $textNode, 'position' => $position];
    }

    /**
     * Cerca, in ordine di documento, il primo nodo di testo idoneo (non
     * dentro un ancestor vietato) che contenga la frase per intero.
     *
     * @return array{0: DOMText, 1: int}|null [nodo di testo, posizione byte-safe (mb) nel nodo]
     */
    private function findInsertionPoint(DOMNode $node, string $anchorText): ?array
    {
        if ($node instanceof DOMText) {
            $position = mb_stripos($node->nodeValue, $anchorText, 0, 'UTF-8');

            if ($position !== false && ! $this->hasForbiddenAncestor($node)) {
                return [$node, $position];
            }

            return null;
        }

        if ($node instanceof DOMElement && in_array(mb_strtolower($node->tagName), self::SKIPPED_SUBTREES, true)) {
            return null;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $found = $this->findInsertionPoint($child, $anchorText);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function hasForbiddenAncestor(DOMNode $node): bool
    {
        $current = $node->parentNode;

        while ($current instanceof DOMElement) {
            if (in_array(mb_strtolower($current->tagName), self::FORBIDDEN_ANCESTORS, true)) {
                return true;
            }

            $current = $current->parentNode;
        }

        return false;
    }

    private function splitAndWrap(DOMDocument $dom, DOMText $textNode, int $position, string $anchorText, string $targetUrl): void
    {
        $fullText = $textNode->nodeValue;
        $before = mb_substr($fullText, 0, $position, 'UTF-8');
        $matched = mb_substr($fullText, $position, mb_strlen($anchorText, 'UTF-8'), 'UTF-8');
        $after = mb_substr($fullText, $position + mb_strlen($anchorText, 'UTF-8'), null, 'UTF-8');

        $parent = $textNode->parentNode;

        $link = $dom->createElement('a');
        $link->setAttribute('href', $targetUrl);
        $link->appendChild($dom->createTextNode($matched));

        if ($before !== '') {
            $parent->insertBefore($dom->createTextNode($before), $textNode);
        }

        $parent->insertBefore($link, $textNode);

        if ($after !== '') {
            $parent->insertBefore($dom->createTextNode($after), $textNode);
        }

        $parent->removeChild($textNode);
    }

    private function innerHtml(DOMDocument $dom, DOMNode $root): string
    {
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
