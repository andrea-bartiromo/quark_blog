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
 * Non scrive nulla sul contenuto salvato: opera solo sulla stringa HTML
 * passata in ingresso e restituisce una nuova stringa, da usare
 * esclusivamente in fase di rendering — stesso principio, stesso pattern
 * DOM (mai una regex su HTML) di TableOfContentsService::build(), con cui
 * questo servizio condivide la pipeline di rendering del corpo articolo.
 */
class ArticleBodyImageService
{
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
        $images = $xpath->query('//img');

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
        }

        $root = $xpath->query('//div[@id="__abi_root__"]')->item(0);

        return $this->innerHtml($dom, $root);
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
