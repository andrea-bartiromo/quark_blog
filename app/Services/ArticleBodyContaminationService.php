<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class ArticleBodyContaminationService
{
    public function __construct(private readonly ArticleBodySanitizer $sanitizer) {}

    /**
     * @return array<int, string>
     */
    public function findings(string $html): array
    {
        $findings = [];

        $patterns = [
            'SCRIPT' => '/<script\b/i',
            'IFRAME' => '/<iframe\b/i',
            'INLINE_STYLE' => '/\sstyle\s*=/i',
            'CHATGPT_DATA_ATTRIBUTE' => '/\sdata-(?:message-id|turn)\s*=/i',
            'CHATGPT_CLASS' => '/\sclass\s*=\s*["\'][^"\']*(?:chatgpt|conversation-turn)[^"\']*["\']/i',
        ];

        foreach ($patterns as $code => $pattern) {
            if (preg_match($pattern, $html) === 1) {
                $findings[] = $code;
            }
        }

        if ($this->containsForeignPlatformUtm($html)) {
            $findings[] = 'FOREIGN_PLATFORM_UTM_SOURCE';
        }

        return $findings;
    }

    /**
     * @return array{before_hash: string, after_hash: string, removed_nodes: int, preview: string}
     */
    public function dryRun(string $html): array
    {
        $clean = $this->stripForeignPlatformUtm($this->sanitizer->sanitize($html));

        return [
            'before_hash' => hash('sha256', $html),
            'after_hash' => hash('sha256', $clean),
            'removed_nodes' => max(0, $this->nodeCount($html) - $this->nodeCount($clean)),
            'preview' => Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($clean)) ?? ''), 180),
        ];
    }

    private function containsForeignPlatformUtm(string $html): bool
    {
        foreach ($this->links($html) as $link) {
            $href = html_entity_decode($link->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));

            if ($host !== '' && ! $this->isKairusHost($host) && $this->hasForeignPlatformUtmSource($href)) {
                return true;
            }
        }

        return false;
    }

    private function stripForeignPlatformUtm(string $html): string
    {
        $dom = $this->document($html);
        $root = $dom->getElementById('__hygiene_root__');

        if ($root === null) {
            return $html;
        }

        foreach ((new DOMXPath($dom))->query('.//a[@href]', $root) ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = html_entity_decode($link->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));

            if ($host === '' || $this->isKairusHost($host) || ! $this->hasForeignPlatformUtmSource($href)) {
                continue;
            }

            $link->setAttribute('href', $this->withoutForeignPlatformUtmSource($href));
        }

        return $this->innerHtml($dom, $root);
    }

    private function hasForeignPlatformUtmSource(string $href): bool
    {
        foreach ($this->rawQueryPairs($href) as $pair) {
            if ($this->isForeignPlatformUtmSourcePair($pair)) {
                return true;
            }
        }

        return false;
    }

    private function withoutForeignPlatformUtmSource(string $href): string
    {
        $fragmentPosition = strpos($href, '#');
        $beforeFragment = $fragmentPosition === false ? $href : substr($href, 0, $fragmentPosition);
        $fragment = $fragmentPosition === false ? '' : substr($href, $fragmentPosition);
        $queryPosition = strpos($beforeFragment, '?');

        if ($queryPosition === false) {
            return $href;
        }

        $base = substr($beforeFragment, 0, $queryPosition);
        $pairs = array_values(array_filter(
            explode('&', substr($beforeFragment, $queryPosition + 1)),
            fn (string $pair): bool => ! $this->isForeignPlatformUtmSourcePair($pair),
        ));

        return $base.($pairs === [] ? '' : '?'.implode('&', $pairs)).$fragment;
    }

    /**
     * @return array<int, string>
     */
    private function rawQueryPairs(string $href): array
    {
        $query = parse_url($href, PHP_URL_QUERY);

        return is_string($query) && $query !== '' ? explode('&', $query) : [];
    }

    private function isForeignPlatformUtmSourcePair(string $pair): bool
    {
        [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');

        return strtolower(urldecode($rawKey)) === 'utm_source'
            && in_array(strtolower(urldecode($rawValue)), ['chatgpt.com', 'chatgpt', 'openai'], true);
    }

    /**
     * @return array<int, DOMElement>
     */
    private function links(string $html): array
    {
        $dom = $this->document($html);
        $root = $dom->getElementById('__hygiene_root__');

        if ($root === null) {
            return [];
        }

        return array_values(array_filter(
            iterator_to_array((new DOMXPath($dom))->query('.//a[@href]', $root) ?: []),
            fn ($node) => $node instanceof DOMElement,
        ));
    }

    private function nodeCount(string $html): int
    {
        $dom = $this->document($html);
        $root = $dom->getElementById('__hygiene_root__');

        if ($root === null) {
            return 0;
        }

        $nodes = (new DOMXPath($dom))->query('.//*', $root);

        return $nodes === false ? 0 : $nodes->length;
    }

    private function isKairusHost(string $host): bool
    {
        return $host === 'kairus.it' || str_ends_with($host, '.kairus.it');
    }

    private function document(string $html): DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="__hygiene_root__">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return $dom;
    }

    private function innerHtml(DOMDocument $dom, DOMElement $root): string
    {
        $html = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $html .= $dom->saveHTML($child);
        }

        return trim($html);
    }
}
