<?php

namespace App\Services\PublicPages;

use DOMDocument;
use DOMXPath;

/**
 * Cantiere 29 (programma 100-cantieri Kairus), dipende dal Cantiere 21
 * (`PublicPageInventory`). `docs/PUBLIC_A11Y_RESPONSIVE_HANDOFF.md`
 * (Cantiere I, programma precedente e distinto da questo) è un audit
 * MANUALE una tantum, limitato a 7 superfici deliberatamente scelte, mai
 * ripetibile automaticamente. `PublicPageInventory` copre invece 14+
 * pagine statiche, i capitoli Turing e 4 tipi di pagina dinamica —
 * incluse pagine (redazione, chi-siamo, contatti, privacy, cookie,
 * termini, rettifiche, metodologia, autore) che quell'audit manuale non
 * ha mai controllato. Questo servizio non duplica quel lavoro: lo
 * trasforma in un controllo ricorrente, a scala sull'intero inventario,
 * per i criteri WCAG realmente verificabili da solo markup HTML (senza
 * un browser reale — `tests/browser/keyboard-navigation.spec.js`,
 * Cantiere 28, copre già skip-link funzionante e anello di focus
 * visibile via tastiera, mai duplicati qui).
 *
 * Sola lettura, nessun effetto collaterale: ogni pagina dell'inventario
 * con un `sample_url` viene richiesta in-process
 * (`InProcessPageFetcher`) e il markup HTML restituito viene analizzato
 * per:
 * - `<html lang="...">` presente (WCAG 3.1.1);
 * - esattamente un `<h1>` in pagina (WCAG 1.3.1/2.4.6);
 * - nessun salto di livello heading, es. h1 seguito direttamente da h3
 *   (WCAG 1.3.1/2.4.6);
 * - landmark `<header>`, `<main>`, `<footer>` presenti (WCAG 1.3.1);
 * - lo skip-link punta a un id realmente presente in pagina (WCAG 2.4.1)
 *   — un controllo statico complementare a quello dinamico del Cantiere
 *   28 (che verifica il comportamento REALE da tastiera in un browser,
 *   non solo che il markup sia sintatticamente corretto);
 * - ogni `<img>` ha l'attributo `alt` (anche vuoto per un'immagine
 *   decorativa: solo l'attributo assente è un finding, WCAG 1.1.1);
 * - ogni `<a href>`/`<button>` ha un nome accessibile (testo, oppure
 *   `aria-label`/`aria-labelledby` — WCAG 2.4.4/4.1.2).
 *
 * Una pagina dell'inventario con `sample_url` nullo (nessun record
 * pubblico ancora esistente) non è un errore: nessun finding, nessuno
 * stato HTTP.
 */
class WcagInternalAudit
{
    public function __construct(
        private readonly PublicPageInventory $inventory,
        private readonly InProcessPageFetcher $fetcher,
    ) {}

    /**
     * @return list<array{key: string, label: string, url: string|null, http_status: int|null, findings: list<string>}>
     */
    public function audit(): array
    {
        return array_map(fn (array $page) => $this->auditPage($page), $this->inventory->pages());
    }

    /**
     * @param  array{key: string, label: string, route_name: string, kind: string, sample_url: string|null}  $page
     * @return array{key: string, label: string, url: string|null, http_status: int|null, findings: list<string>}
     */
    private function auditPage(array $page): array
    {
        if ($page['sample_url'] === null) {
            return ['key' => $page['key'], 'label' => $page['label'], 'url' => null, 'http_status' => null, 'findings' => []];
        }

        $response = $this->fetcher->fetch($page['sample_url']);
        $status = $response->getStatusCode();

        $result = ['key' => $page['key'], 'label' => $page['label'], 'url' => $page['sample_url'], 'http_status' => $status, 'findings' => []];

        if ($status !== 200) {
            $result['findings'][] = "Stato HTTP inatteso: {$status} (atteso 200 per una pagina considerata pubblicamente raggiungibile).";

            return $result;
        }

        $result['findings'] = $this->auditHtml((string) $response->getContent());

        return $result;
    }

    /**
     * @return list<string>
     */
    private function auditHtml(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $findings = [];

        $htmlElement = $dom->getElementsByTagName('html')->item(0);
        if ($htmlElement === null || trim($htmlElement->getAttribute('lang')) === '') {
            $findings[] = 'Attributo lang assente su <html> (WCAG 3.1.1).';
        }

        $h1Count = $dom->getElementsByTagName('h1')->length;
        if ($h1Count === 0) {
            $findings[] = 'Nessun <h1> in pagina (WCAG 1.3.1/2.4.6).';
        } elseif ($h1Count > 1) {
            $findings[] = "Più di un <h1> in pagina ({$h1Count}) (WCAG 1.3.1).";
        }

        $headingJump = $this->firstHeadingLevelJump($xpath);
        if ($headingJump !== null) {
            $findings[] = $headingJump;
        }

        foreach (['header', 'main', 'footer'] as $landmark) {
            if ($dom->getElementsByTagName($landmark)->length === 0) {
                $findings[] = "Landmark <{$landmark}> assente (WCAG 1.3.1).";
            }
        }

        $skipLinkFinding = $this->skipLinkFinding($xpath);
        if ($skipLinkFinding !== null) {
            $findings[] = $skipLinkFinding;
        }

        $missingAlt = 0;
        foreach ($dom->getElementsByTagName('img') as $img) {
            if (! $img->hasAttribute('alt')) {
                $missingAlt++;
            }
        }
        if ($missingAlt > 0) {
            $findings[] = "{$missingAlt} <img> senza attributo alt (WCAG 1.1.1).";
        }

        $inaccessibleControls = $this->countInaccessibleControls($xpath);
        if ($inaccessibleControls > 0) {
            $findings[] = "{$inaccessibleControls} link/pulsante senza nome accessibile (nessun testo, aria-label o aria-labelledby) (WCAG 2.4.4/4.1.2).";
        }

        return $findings;
    }

    private function firstHeadingLevelJump(DOMXPath $xpath): ?string
    {
        $previousLevel = 0;
        foreach ($xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6') as $heading) {
            $level = (int) substr($heading->nodeName, 1);
            if ($previousLevel > 0 && $level > $previousLevel + 1) {
                return "Salto di livello heading da h{$previousLevel} a h{$level} (WCAG 1.3.1/2.4.6).";
            }
            $previousLevel = $level;
        }

        return null;
    }

    private function skipLinkFinding(DOMXPath $xpath): ?string
    {
        $skipLinks = $xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " skip-link ")]');
        if ($skipLinks->length === 0) {
            return 'Skip-link assente (WCAG 2.4.1).';
        }

        foreach ($skipLinks as $link) {
            $href = $link->getAttribute('href');
            if ($href === '' || $href[0] !== '#' || $href === '#') {
                return "Skip-link con href non valido: \"{$href}\" (WCAG 2.4.1).";
            }

            $targetId = substr($href, 1);
            if ($xpath->query('//*[@id='.$this->xpathLiteral($targetId).']')->length === 0) {
                return "Skip-link punta a un id inesistente: #{$targetId} (WCAG 2.4.1).";
            }
        }

        return null;
    }

    private function countInaccessibleControls(DOMXPath $xpath): int
    {
        $count = 0;
        foreach ($xpath->query('//a[@href] | //button') as $control) {
            $text = trim($control->textContent);
            $ariaLabel = trim($control->getAttribute('aria-label'));
            $ariaLabelledby = trim($control->getAttribute('aria-labelledby'));

            if ($text === '' && $ariaLabel === '' && $ariaLabelledby === '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Costruisce un literal XPath 1.0 sicuro anche se $value contiene
     * apici singoli o doppi (XPath 1.0 non ha un modo nativo di
     * escapare le virgolette dentro un literal).
     */
    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (! str_contains($value, '"')) {
            return "\"{$value}\"";
        }

        $parts = array_map(fn (string $part) => "'{$part}'", explode("'", $value));

        return 'concat('.implode(', "\'", ', $parts).')';
    }
}
