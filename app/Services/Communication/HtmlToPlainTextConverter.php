<?php

namespace App\Services\Communication;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Conversione HTML -> testo semplice, deterministica e pura. Non un
 * semplice strip_tags(): preserva la struttura leggibile (titoli,
 * paragrafi separati da riga vuota, link resi come "testo (url)", liste
 * puntate con prefisso "- "), tollera HTML malformato (libxml in
 * modalità permissiva, mai un'eccezione per markup non valido) e
 * preserva correttamente Unicode/emoji (DOMDocument con dichiarazione
 * encoding esplicita, mai una doppia decodifica).
 */
class HtmlToPlainTextConverter
{
    public function convert(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;

        // Forza UTF-8 indipendentemente da un'eventuale (assente) meta
        // charset nel frammento — senza questo prefisso DOMDocument
        // assume Latin-1 e corrompe qualunque carattere multibyte
        // (accenti italiani, emoji).
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="__root__">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            // Markup irrecuperabile anche in modalità permissiva: mai
            // un'eccezione qui, il fallback più sicuro è il testo grezzo
            // con i tag rimossi.
            return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        }

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="__root__"]')->item(0);

        if (! $root instanceof DOMElement) {
            return trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
        }

        $blocks = [];
        $this->renderNode($root, $blocks);

        $text = implode("\n\n", array_filter($blocks, fn (string $b) => trim($b) !== ''));

        // Normalizza spazi orizzontali senza toccare le interruzioni di
        // paragrafo già inserite sopra.
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<string>  $blocks
     */
    private function renderNode(DOMNode $node, array &$blocks): void
    {
        static $blockTags = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'tr', 'blockquote'];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $current = end($blocks);
                $piece = preg_replace('/\s+/u', ' ', $child->wholeText) ?? '';

                if (trim($piece) === '') {
                    continue;
                }

                if ($current === false) {
                    $blocks[] = ltrim($piece);
                } else {
                    $blocks[array_key_last($blocks)] .= $piece;
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'script' || $tag === 'style') {
                continue;
            }

            if ($tag === 'br') {
                $blocks[] = '';

                continue;
            }

            if ($tag === 'a') {
                $href = trim($child->getAttribute('href'));
                $label = trim($this->collectText($child));

                $current = end($blocks);
                $rendered = $href !== '' && $href !== $label
                    ? ($label !== '' ? "{$label} ({$href})" : $href)
                    : ($label !== '' ? $label : $href);

                if ($current === false) {
                    $blocks[] = $rendered;
                } else {
                    $blocks[array_key_last($blocks)] .= $rendered;
                }

                continue;
            }

            if ($tag === 'li') {
                $blocks[] = '- '.trim($this->collectText($child));

                continue;
            }

            if (in_array($tag, $blockTags, true)) {
                $blocks[] = '';
                $this->renderNode($child, $blocks);
                $blocks[] = '';

                continue;
            }

            $this->renderNode($child, $blocks);
        }
    }

    private function collectText(DOMElement $element): string
    {
        $parts = [];
        $this->renderNode($element, $parts);

        return preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts, fn (string $p) => trim($p) !== ''))) ?? '';
    }
}
