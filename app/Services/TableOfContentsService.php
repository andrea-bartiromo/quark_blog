<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class TableOfContentsService
{
    /**
     * Individua tutti gli h2/h3 nell'HTML fornito, assegna un id univoco a
     * quelli che non ne hanno già uno (senza mai modificare un id
     * esistente), e restituisce sia l'HTML aggiornato sia la struttura ad
     * albero per l'indice (h3 annidati sotto l'h2 che li precede).
     *
     * Non scrive nulla sul contenuto salvato: opera solo sulla stringa HTML
     * passata in ingresso e restituisce una nuova stringa, da usare
     * esclusivamente in fase di rendering.
     *
     * @return array{html: string, items: array<int, array{id: string, text: string, level: int, children: array<int, array{id: string, text: string, level: int}>}>}
     */
    public function build(string $html): array
    {
        $trimmed = trim($html);

        if ($trimmed === '') {
            return ['html' => $html, 'items' => []];
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__toc_root__">'.$trimmed.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//div[@id="__toc_root__"]')->item(0);

        // Trovato in review (Codex, su un servizio gemello che condivide
        // esattamente questo pattern): un tag di chiusura senza apertura
        // nel corpo salvato (es. un "</div>" residuo, tollerato dai
        // browser al momento del salvataggio) puo' essere interpretato dal
        // parser HTML come chiusura ANTICIPATA di questo wrapper
        // sintetico — tutto cio' che segue nel frammento finisce fuori da
        // $root, come fratello successivo nel documento, non piu' come suo
        // discendente. innerHtml() lo perderebbe in silenzio, troncando
        // l'articolo pubblicato (testo, heading successivi ED eventuali
        // immagini). Riprodotto empiricamente: un "</div>" a meta' corpo
        // faceva sparire tutto il testo successivo dal rendering. Se il
        // wrapper non e' rimasto l'unico nodo di primo livello del
        // documento sintetico, il parsing e' compromesso: meglio
        // restituire l'HTML originale invariato (nessun id assegnato
        // quella volta, TOC assente) che rischiare di troncare un
        // articolo pubblicato.
        if ($root === null || $root->nextSibling !== null) {
            return ['html' => $html, 'items' => []];
        }

        $headings = $xpath->query('.//h2 | .//h3', $root);

        if ($headings === false || $headings->length === 0) {
            return ['html' => $html, 'items' => []];
        }

        $usedIds = [];
        $flat = [];

        foreach ($headings as $heading) {
            /** @var DOMElement $heading */
            $text = trim($heading->textContent);

            if ($text === '') {
                continue;
            }

            $id = $heading->getAttribute('id');

            if ($id === '') {
                $id = $this->uniqueId($text, $usedIds);
                $heading->setAttribute('id', $id);
            } else {
                // Requisito: un id già presente non va mai modificato, anche
                // se coincide con uno già assegnato altrove.
                $usedIds[$id] = true;
            }

            $flat[] = [
                'id' => $id,
                'text' => $text,
                'level' => (int) substr($heading->nodeName, 1),
            ];
        }

        $renderedHtml = $this->innerHtml($dom, $root);

        return [
            'html' => $renderedHtml,
            // La TOC va mostrata solo con almeno 2 titoli complessivi
            // (h2+h3); gli id restano comunque assegnati a prescindere,
            // utili per link diretti/SEO anche con un solo titolo.
            'items' => count($flat) >= 2 ? $this->nest($flat) : [],
        ];
    }

    private function innerHtml(DOMDocument $dom, \DOMNode $root): string
    {
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }

    /**
     * @param  array<int, array{id: string, text: string, level: int}>  $flat
     * @return array<int, array{id: string, text: string, level: int, children: array<int, array{id: string, text: string, level: int}>}>
     */
    private function nest(array $flat): array
    {
        $items = [];
        $lastH2Index = null;

        foreach ($flat as $entry) {
            $entry['children'] = [];

            if ($entry['level'] === 2 || $lastH2Index === null) {
                $items[] = $entry;
                $lastH2Index = count($items) - 1;

                continue;
            }

            $items[$lastH2Index]['children'][] = $entry;
        }

        return $items;
    }

    /**
     * @param  array<string, bool>  $usedIds
     */
    private function uniqueId(string $text, array &$usedIds): string
    {
        $base = Str::slug($text);

        if ($base === '') {
            $base = 'sezione';
        }

        $id = $base;
        $suffix = 2;

        while (isset($usedIds[$id])) {
            $id = $base.'-'.$suffix;
            $suffix++;
        }

        $usedIds[$id] = true;

        return $id;
    }
}
