<?php

namespace App\Services\LinkHealth;

use DOMDocument;
use DOMElement;

/**
 * Cantiere 25 (programma 100-cantieri Kairus). A differenza di
 * App\Services\ArticleLinkInsertionService::internalArticleLinkOccurrences()
 * (che riconosce SOLO il pattern /articolo/{slug}, per il suo scopo
 * specifico di conteggio/inserimento link tra articoli), questa classe
 * estrae OGNI collegamento `<a href>` dal corpo di un articolo e lo
 * classifica per tipo — passo preliminare per
 * LinkReachabilityAuditService, che deve verificare anche i
 * collegamenti interni verso categoria/percorso/pagine statiche e i
 * collegamenti esterni, mai coperti dall'audit esistente (Cantiere 25
 * dipende dal Cantiere 21 proprio per la nozione di "pagina pubblica
 * raggiungibile" riusata qui).
 */
class ArticleLinkExtractor
{
    /**
     * @return list<array{url: string, anchorText: string, type: string}>
     */
    public function extract(string $html): array
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

        $links = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            /** @var DOMElement $anchor */
            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || $href[0] === '#') {
                continue;
            }

            if (preg_match('~^(mailto|tel|javascript):~i', $href) === 1) {
                continue;
            }

            $links[] = [
                'url' => $href,
                'anchorText' => trim($anchor->textContent),
                'type' => $this->classify($href),
            ];
        }

        return $links;
    }

    /**
     * 'internal' = path relativo, o URL assoluto sullo stesso host
     * dell'applicazione (config('app.url')) — 'external' altrimenti.
     */
    private function classify(string $href): string
    {
        $host = parse_url($href, PHP_URL_HOST);

        if ($host === null) {
            return 'internal';
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host === $appHost ? 'internal' : 'external';
    }

    /**
     * Il path da usare per una richiesta in-process a un link interno:
     * per un URL assoluto sullo stesso host, solo path+query — per un
     * path già relativo, invariato.
     */
    public function toInternalPath(string $href): string
    {
        if (parse_url($href, PHP_URL_HOST) === null) {
            return $href;
        }

        $path = parse_url($href, PHP_URL_PATH) ?? '/';
        $query = parse_url($href, PHP_URL_QUERY);

        return $query !== null ? "{$path}?{$query}" : $path;
    }
}
