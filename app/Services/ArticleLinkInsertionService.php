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
     *                      stata trovata in una posizione valida, o l'href
     *                      non supera la validazione di sicurezza (nessuna
     *                      modifica in entrambi i casi).
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
     * @return array{dom: DOMDocument, root: DOMNode, textNode: DOMText, position: int}|null
     */
    private function locateInsertionPoint(string $bodyHtml, string $anchorText): ?array
    {
        $anchorText = trim($anchorText);

        if ($anchorText === '' || trim($bodyHtml) === '') {
            return null;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__link_insert_root__">'.$bodyHtml.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

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
