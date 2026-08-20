<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * S7 — sanifica l'HTML inviato dal ruolo 'author' (Redazione\
 * StoreArticleRequest/UpdateArticleRequest validavano `body` solo come
 * 'required': nessuna verifica del contenuto). Il TinyMCE lato client
 * (resources/views/redazione/article-form.blade.php) è solo una
 * convenienza UI — non impedisce a una richiesta POST diretta di portare
 * qualunque stringa, incluso <script>. Una volta approvato da un editor
 * (Admin\ReviewController::approve() non tocca mai `body`), il contenuto
 * viene renderizzato RAW su articolo.blade.php per ogni visitatore
 * pubblico quando "sembra HTML" — vedi ArticleBodyStoredXssTest.
 *
 * Allowlist deliberatamente ristretta alla forma reale prodotta dalla
 * toolbar TinyMCE configurata per questo ruolo (blocks, bold/italic,
 * liste, link — niente immagini/tabelle inserite dentro il body, la
 * cover è un campo separato): p, h1-h6, strong/b, em/i, u, ul/ol/li,
 * blockquote, a, br. Ogni altro elemento viene rimosso mantenendone il
 * contenuto testuale (non l'intero paragrafo); ogni attributo `on*` e
 * ogni href con schema javascript:/vbscript:/data: viene rimosso.
 * Non tocca il body scritto da editor/admin (Admin\ArticleController):
 * quel ruolo ha già pieno controllo del sito (upload, cancellazione,
 * gestione utenti) — stesso modello di fiducia di qualunque admin CMS,
 * fuori dal confine di privilegio che questa sanificazione protegge.
 */
class ArticleBodySanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a',
    ];

    /** @var array<string, array<int, string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    private const BLOCKED_URL_SCHEMES = ['javascript', 'vbscript', 'data'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        // Avvolto in un contenitore radice: un frammento (non un documento
        // HTML completo) fa sì che DOMDocument lo interpreti correttamente
        // senza inferire <html>/<body> propri, che poi andrebbero rimossi.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__sanitize_root__">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__sanitize_root__');

        if ($root === null) {
            // HTML irreparabilmente malformato: mai restituire l'input
            // grezzo non sanificato, meglio testo semplice.
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $this->sanitizeNode($dom, $root);

        $inner = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return trim($inner);
    }

    private function sanitizeNode(DOMDocument $dom, DOMNode $node): void
    {
        // Copia statica: rimuovere/sostituire nodi durante l'iterazione
        // altrimenti salta elementi (childNodes è una live NodeList).
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Rimuove solo l'elemento, non il suo contenuto
                    // testuale/figli leciti: un <script> viene eliminato
                    // interamente (nessun figlio testuale legittimo da
                    // preservare), uno <span> sconosciuto lascia il testo.
                    if ($tag === 'script' || $tag === 'style') {
                        $node->removeChild($child);

                        continue;
                    }

                    $this->sanitizeNode($dom, $child);

                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                $this->sanitizeAttributes($child, $tag);
                $this->sanitizeNode($dom, $child);
            }
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'href' && $this->hasBlockedScheme($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        // target="_blank" senza rel="noopener" espone la pagina di
        // destinazione a window.opener — imposto a prescindere da cosa
        // l'autore abbia scritto in rel, non solo quando è assente.
        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function hasBlockedScheme(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme !== null && in_array(strtolower($scheme), self::BLOCKED_URL_SCHEMES, true);
    }
}
