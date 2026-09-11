<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

class ArticleManualSourcesDetector
{
    public function hasManualSourcesSection(?string $body): bool
    {
        if (blank($body)) {
            return false;
        }

        return $this->hasHtmlSourcesHeading($body)
            || $this->hasMarkdownSourcesHeading($body);
    }

    private function hasHtmlSourcesHeading(string $body): bool
    {
        if (! str_contains($body, '<')) {
            return false;
        }

        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!DOCTYPE html><html><body>'.$body.'</body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );

            $headings = (new DOMXPath($document))->query('//h1|//h2|//h3|//h4|//h5|//h6');

            foreach ($headings ?: [] as $heading) {
                if ($heading instanceof DOMElement && $this->isSourcesHeading($heading->textContent)) {
                    return true;
                }
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        return false;
    }

    private function hasMarkdownSourcesHeading(string $body): bool
    {
        $lines = preg_split('/\\R/u', $body) ?: [];

        foreach ($lines as $index => $line) {
            if (preg_match('/^\\h{0,3}#{1,6}\\h+(.+?)\\h*#*\\h*$/u', $line, $matches)
                && $this->isSourcesHeading($matches[1])) {
                return true;
            }

            if ($index < count($lines) - 1
                && trim($line) !== ''
                && preg_match('/^\\h{0,3}(?:=+|-+)\\h*$/u', $lines[$index + 1])
                && $this->isSourcesHeading($line)) {
                return true;
            }
        }

        return false;
    }

    private function isSourcesHeading(string $heading): bool
    {
        $normalized = html_entity_decode(strip_tags($heading), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/[*_\\x60]+/u', '', $normalized) ?? '';
        $normalized = preg_replace('/[\\s\\x{00A0}]+/u', ' ', $normalized) ?? '';
        $normalized = mb_strtolower(trim($normalized), 'UTF-8');
        $normalized = rtrim($normalized, ':');

        return in_array($normalized, ['fonti', 'fonti principali', 'fonti primarie'], true);
    }
}
