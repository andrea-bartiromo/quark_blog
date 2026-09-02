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
        $root = $dom->documentElement;

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
        $rcdata = [];
        $comments = [];
        $nonce = bin2hex(random_bytes(8));
        $protected = preg_replace_callback(
            '~(?P<open><(?P<tag>textarea|title)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)(?P<body>.*?)(?P<close></\k<tag>\s*>|\z)~is',
            function (array $match) use (&$rcdata, $nonce): string {
                $placeholder = '__KAIRUS_HYGIENE_RCDATA_'.$nonce.'_'.count($rcdata).'__';
                $rcdata[$placeholder] = $match['body'];

                return $match['open'].$placeholder.$match['close'];
            },
            $html,
        ) ?? $html;
        $protected = preg_replace_callback('/<!--.*?-->/s', function (array $match) use (&$comments, $nonce): string {
            $placeholder = '__KAIRUS_HYGIENE_COMMENT_'.$nonce.'_'.count($comments).'__';
            $comments[$placeholder] = $match[0];

            return $placeholder;
        }, $protected) ?? $protected;

        $withoutEmbeddedContent = preg_replace(
            '~<(?P<tag>script|iframe)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>(?:.*?</\k<tag>\s*>|.*\z)|</(?:script|iframe)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~is',
            '',
            $protected,
        ) ?? $protected;

        $clean = preg_replace_callback(
            '~<(?P<name>[a-z][a-z0-9:-]*)(?P<attributes>(?:[^>"\']|"[^"]*"|\'[^\']*\')*)>~is',
            fn (array $match): string => '<'.$match['name'].$this->cleanTagAttributes($match['name'], $match['attributes']).'>',
            $withoutEmbeddedContent,
        ) ?? $withoutEmbeddedContent;

        return strtr(strtr($clean, $comments), $rcdata);
    }

    private function cleanTagAttributes(string $tag, string $attributes): string
    {
        return preg_replace_callback(
            '~(?P<leading>\s+)(?P<name>[^\s=/>]+)(?P<assignment>\s*=\s*(?:(?P<quote>["\'])(?P<quoted>.*?)\k<quote>|(?P<unquoted>[^\s>]+)))?~s',
            function (array $match) use ($tag): string {
                $name = strtolower($match['name']);

                if (in_array($name, ['style', 'data-message-id', 'data-turn'], true)) {
                    return '';
                }

                if (($match['assignment'] ?? '') === '') {
                    return $match[0];
                }

                $quote = $match['quote'] ?? '';
                $value = $quote !== '' ? ($match['quoted'] ?? '') : ($match['unquoted'] ?? '');
                $cleanValue = $value;

                if ($name === 'class') {
                    $cleanValue = $this->withoutContaminatedClassTokens($value);

                    if (trim($cleanValue) === '') {
                        return '';
                    }
                }

                if (strtolower($tag) === 'a' && $name === 'href') {
                    $decodedHref = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $host = strtolower((string) parse_url($decodedHref, PHP_URL_HOST));

                    if ($host !== '' && ! $this->isKairusHost($host)) {
                        $cleanValue = $this->withoutForeignPlatformUtmSourceFromAttribute($value);
                    }
                }

                if ($cleanValue === $value) {
                    return $match[0];
                }

                return $match['leading'].$match['name']
                    .$this->replaceAssignmentValue($match['assignment'], $value, $cleanValue, $quote);
            },
            $attributes,
        ) ?? $attributes;
    }

    private function replaceAssignmentValue(string $assignment, string $old, string $new, string $quote): string
    {
        if ($quote !== '') {
            $start = strpos($assignment, $quote);

            return $start === false
                ? $assignment
                : substr($assignment, 0, $start + 1).$new.substr($assignment, $start + 1 + strlen($old));
        }

        $start = strrpos($assignment, $old);

        return $start === false
            ? $assignment
            : substr($assignment, 0, $start).$new.substr($assignment, $start + strlen($old));
    }

    private function withoutForeignPlatformUtmSourceFromAttribute(string $href): string
    {
        $fragmentPosition = $this->literalFragmentPosition($href);
        $beforeFragment = $fragmentPosition === false ? $href : substr($href, 0, $fragmentPosition);
        $fragment = $fragmentPosition === false ? '' : substr($href, $fragmentPosition);
        $queryPosition = strpos($beforeFragment, '?');

        if ($queryPosition === false) {
            return $href;
        }

        $base = substr($beforeFragment, 0, $queryPosition);
        $tokens = preg_split('/(&(?:amp;|#(?:0*38|x0*26);)?)/i', substr($beforeFragment, $queryPosition + 1), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $kept = [];

        for ($index = 0; $index < count($tokens); $index += 2) {
            $pair = $tokens[$index];

            if ($this->isForeignPlatformUtmSourcePair($pair)) {
                continue;
            }

            $kept[] = [
                'delimiter' => $index === 0 ? '' : $tokens[$index - 1],
                'pair' => $pair,
            ];
        }

        if ($kept === []) {
            return $base.$fragment;
        }

        $query = $kept[0]['pair'];
        foreach (array_slice($kept, 1) as $pair) {
            $query .= $pair['delimiter'].$pair['pair'];
        }

        return $base.'?'.$query.$fragment;
    }

    private function literalFragmentPosition(string $href): int|false
    {
        $offset = 0;

        while (($position = strpos($href, '#', $offset)) !== false) {
            if ($position === 0 || $href[$position - 1] !== '&') {
                return $position;
            }

            $offset = $position + 1;
        }

        return false;
    }

    private function withoutContaminatedClassTokens(string $value): string
    {
        $separator = '(?:\s|&(?:#(?:0*(?:9|10|12|13|32)|x0*(?:9|a|c|d|20));|Tab;|NewLine;))+';
        $tokens = preg_split('/('.$separator.')/i', $value, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $clean = '';
        $pendingSeparator = '';

        foreach ($tokens as $index => $token) {
            if ($index % 2 === 1) {
                $pendingSeparator = $token;

                continue;
            }

            if ($token === '' || $this->isContaminatedClass(html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                continue;
            }

            $clean .= ($clean === '' ? '' : $pendingSeparator).$token;
            $pendingSeparator = '';
        }

        return $clean;
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
        $root = $dom->documentElement;

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
        $root = $dom->documentElement;

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
        $html = preg_replace_callback(
            '~(?P<open><(?P<tag>textarea|title)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)(?P<body>.*?)(?P<close></\k<tag>\s*>|\z)~is',
            fn (array $match): string => $match['open']
                .str_replace(['<', '>'], ['&lt;', '&gt;'], $match['body'])
                .$match['close'],
            $html,
        ) ?? $html;
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapper = 'kairus-hygiene-'.bin2hex(random_bytes(8));
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><'.$wrapper.' id="__hygiene_root__">'.$html.'</'.$wrapper.'>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return $dom;
    }
}
