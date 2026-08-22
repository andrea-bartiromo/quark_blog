<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Aggiunge loading="lazy" (e decoding="async") alle immagini nel corpo di
 * un articolo, quando l'HTML salvato non specifica gia' un attributo
 * loading — l'editor TinyMCE non lo imposta mai automaticamente. La cover
 * dell'articolo (fuori da questo corpo, vedi articles/partials/hero.blade.php)
 * resta sempre eager: e' quasi sempre l'elemento LCP della pagina. Ogni
 * immagine nel corpo, per costruzione, appare sempre più in basso nella
 * pagina rispetto alla cover — non e' mai a rischio di essere l'elemento
 * LCP, quindi il lazy loading qui non compromette mai le Core Web Vitals.
 *
 * S2-B: nello stesso passaggio, aggiunge anche width/height (dimensioni
 * intrinseche reali) alle immagini locali che ne sono prive — il CSS
 * dell'articolo (.article-premium__body img{width:100%}) non riserva mai
 * spazio verticale senza di esse, quindi ogni immagine del corpo priva di
 * width/height e' oggi un candidato CLS reale al caricamento. Nessuna
 * immagine esterna viene MAI scaricata per questo: solo immagini il cui
 * src risolve a un file realmente presente sotto public/assets/img/ (lo
 * stesso unico root pubblico locale usato ovunque nel sistema media, vedi
 * ResponsiveImageVariantService) vengono lette, e solo con getimagesize()
 * in lettura — nessun costo GD, nessuna scrittura. Un'immagine esterna, un
 * src assente/malformato o un file locale non leggibile lasciano
 * l'immagine esattamente come nell'HTML originale: mai un errore, mai un
 * fallimento del rendering.
 *
 * Non scrive nulla sul contenuto salvato: opera solo sulla stringa HTML
 * passata in ingresso e restituisce una nuova stringa, da usare
 * esclusivamente in fase di rendering — stesso principio, stesso pattern
 * DOM (mai una regex su HTML) di TableOfContentsService::build(), con cui
 * questo servizio condivide la pipeline di rendering del corpo articolo.
 */
class ArticleBodyImageService
{
    /**
     * Prefisso di percorso pubblico sotto cui vive OGNI immagine locale
     * gestita da questa applicazione (Libreria media, copertine, categorie,
     * varianti responsive — vedi ResponsiveImageVariantService e
     * ImageService). Un src che non ricade sotto questo prefisso, una volta
     * normalizzato a percorso, non e' mai trattato come locale: e' la
     * stessa distinzione "interno vs esterno" richiesta esplicitamente
     * dalla missione S2-B, applicata nel modo piu' conservativo possibile
     * (un falso negativo — un'immagine locale non riconosciuta come tale —
     * lascia semplicemente l'HTML invariato; un falso positivo, che
     * proverebbe a leggere un file fuori da questo root, non e' invece mai
     * possibile per costruzione).
     */
    private const LOCAL_IMAGE_PATH_PREFIX = '/assets/img/';

    public function applyLazyLoading(string $html): string
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return $html;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__abi_root__">'.$trimmed.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//div[@id="__abi_root__"]')->item(0);

        // Trovato in review (Codex): un tag di chiusura senza apertura nel
        // frammento originale (es. "...</div>..." — accettato quando
        // l'articolo fu salvato, perche' i browser lo tollerano) puo'
        // essere interpretato dal parser HTML come chiusura ANTICIPATA di
        // questo wrapper sintetico: tutto cio' che segue nel frammento
        // finisce fuori da $root, come fratello successivo nel documento,
        // non piu' come suo discendente — innerHtml() lo perderebbe in
        // silenzio, troncando l'articolo pubblicato (testo E immagini
        // successive). Se il wrapper non e' rimasto l'unico nodo di primo
        // livello del documento sintetico, il parsing e' compromesso:
        // meglio restituire l'HTML originale invariato (nessun
        // loading="lazy" aggiunto quella volta) che rischiare di troncare
        // un articolo pubblicato.
        if ($root === null || $root->nextSibling !== null) {
            return $html;
        }

        $images = $xpath->query('.//img', $root);

        if ($images === false || $images->length === 0) {
            return $html;
        }

        foreach ($images as $image) {
            /** @var DOMElement $image */
            if (! $image->hasAttribute('loading')) {
                $image->setAttribute('loading', 'lazy');
            }

            if (! $image->hasAttribute('decoding')) {
                $image->setAttribute('decoding', 'async');
            }

            $this->applyIntrinsicSize($image);
        }

        return $this->innerHtml($dom, $root);
    }

    /**
     * Aggiunge width/height reali all'immagine SE E SOLO SE:
     *  - non ha gia' ne' width ne' height (mai sovrascrivere un valore
     *    scelto deliberatamente da chi ha scritto l'articolo);
     *  - il suo src risolve, in modo verificabile e senza ambiguita', a un
     *    file sotto public/assets/img/ realmente presente e leggibile come
     *    immagine.
     *
     * Qualunque altro caso (src esterno, assente, malformato, file
     * mancante, file illeggibile) lascia l'elemento invariato: questo
     * metodo non solleva mai un'eccezione e non ha alcun effetto collaterale
     * osservabile oltre all'eventuale aggiunta dei due attributi.
     */
    private function applyIntrinsicSize(DOMElement $image): void
    {
        if ($image->hasAttribute('width') || $image->hasAttribute('height')) {
            return;
        }

        $absolutePath = $this->resolveLocalImagePath($image->getAttribute('src'));

        if ($absolutePath === null) {
            return;
        }

        $info = @getimagesize($absolutePath);

        if ($info === false || ! isset($info[0], $info[1]) || $info[0] <= 0 || $info[1] <= 0) {
            return;
        }

        $image->setAttribute('width', (string) $info[0]);
        $image->setAttribute('height', (string) $info[1]);
    }

    /**
     * Risolve un src di <img> all'assoluto percorso filesystem locale
     * corrispondente, solo quando punta in modo inequivocabile sotto
     * public/assets/img/ — lo stesso, unico root pubblico locale usato da
     * tutta la pipeline media (vedi LOCAL_IMAGE_PATH_PREFIX). Restituisce
     * null per qualunque src esterno, assente, vuoto o altrimenti non
     * risolvibile con certezza: nessun tentativo di rete, nessuna euristica
     * indulgente.
     */
    private function resolveLocalImagePath(string $src): ?string
    {
        $src = trim($src);

        if ($src === '') {
            return null;
        }

        $host = parse_url($src, PHP_URL_HOST);
        $path = parse_url($src, PHP_URL_PATH);

        if ($path === null || $path === false || $path === '') {
            return null;
        }

        if ($host !== null) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            if ($appHost === null || $appHost === false || strcasecmp($host, (string) $appHost) !== 0) {
                return null;
            }
        }

        $path = rawurldecode($path);

        if (! str_starts_with($path, self::LOCAL_IMAGE_PATH_PREFIX) || str_contains($path, '..')) {
            return null;
        }

        $absolute = public_path(ltrim($path, '/'));

        return is_file($absolute) ? $absolute : null;
    }

    private function innerHtml(DOMDocument $dom, \DOMNode $root): string
    {
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
