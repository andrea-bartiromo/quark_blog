<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

class ArticleBodyContaminationService
{
    /**
     * @return array<int, string>
     */
    public function findings(string $html): array
    {
        $findings = [];
        $dom = $this->document($html);
        $root = $dom->getElementById('__hygiene_root__');

        if ($root === null) {
            return $findings;
        }

        $elements = $this->elements($root);

        if ($this->containsTag($elements, 'script')) {
            $findings[] = 'SCRIPT';
        }

        if ($this->containsTag($elements, 'iframe')) {
            $findings[] = 'IFRAME';
        }

        if ($this->containsAttribute($elements, ['style'])) {
            $findings[] = 'INLINE_STYLE';
        }

        if ($this->containsAttribute($elements, ['data-message-id', 'data-turn'])) {
            $findings[] = 'CHATGPT_DATA_ATTRIBUTE';
        }

        if ($this->containsContaminatedClass($elements)) {
            $findings[] = 'CHATGPT_CLASS';
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
        $clean = $this->cleanContamination($html);

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
            $href = $link->getAttribute('href');
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));

            if ($host !== '' && ! $this->isKairusHost($host) && $this->hasForeignPlatformUtmSource($href)) {
                return true;
            }
        }

        return false;
    }

    private function cleanContamination(string $html): string
    {
        $dom = $this->document($html);
        $root = $dom->getElementById('__hygiene_root__');

        if ($root === null) {
            return $html;
        }

        foreach ($this->elements($root) as $element) {
            if (in_array(strtolower($element->tagName), ['script', 'iframe'], true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            foreach (['style', 'data-message-id', 'data-turn'] as $attribute) {
                $element->removeAttribute($attribute);
            }

            if ($element->hasAttribute('class')) {
                $classes = preg_split('/\s+/u', trim($element->getAttribute('class'))) ?: [];
                $classes = array_values(array_filter(
                    $classes,
                    fn (string $class): bool => ! $this->isContaminatedClass($class),
                ));

                if ($classes === []) {
                    $element->removeAttribute('class');
                } else {
                    $element->setAttribute('class', implode(' ', $classes));
                }
            }
        }

        foreach ((new DOMXPath($dom))->query('.//a[@href]', $root) ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = $link->getAttribute('href');
            $host = strtolower((string) parse_url($href, PHP_URL_HOST));

            if ($host === '' || $this->isKairusHost($host) || ! $this->hasForeignPlatformUtmSource($href)) {
                continue;
            }

            $link->setAttribute('href', $this->withoutForeignPlatformUtmSource($href));
        }

        return $this->innerHtml($dom, $root);
    }

    /**
     * @param  array<int, DOMElement>  $elements
     */
    private function containsTag(array $elements, string $tag): bool
    {
        foreach ($elements as $element) {
            if (strtolower($element->tagName) === $tag) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, DOMElement>  $elements
     * @param  array<int, string>  $attributes
     */
    private function containsAttribute(array $elements, array $attributes): bool
    {
        foreach ($elements as $element) {
            foreach ($attributes as $attribute) {
                if ($element->hasAttribute($attribute)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, DOMElement>  $elements
     */
    private function containsContaminatedClass(array $elements): bool
    {
        foreach ($elements as $element) {
            $classes = preg_split('/\s+/u', trim($element->getAttribute('class'))) ?: [];

            foreach ($classes as $class) {
                if ($this->isContaminatedClass($class)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isContaminatedClass(string $class): bool
    {
        $class = strtolower($class);

        return str_contains($class, 'chatgpt') || str_contains($class, 'conversation-turn');
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

    /**
     * @return array<int, DOMElement>
     */
    private function elements(DOMElement $root): array
    {
        return array_values(array_filter(
            iterator_to_array($root->getElementsByTagName('*')),
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
