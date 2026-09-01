<?php

namespace App\Services\Articles;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class ArticleSourcePresenter
{
    /**
     * @return Collection<int, array{label: string, url: ?string}>
     */
    public function present(?string $primarySources, ?string $legacyBodySources = null): Collection
    {
        return collect([$primarySources, $legacyBodySources])
            ->filter(fn (?string $value): bool => filled($value))
            ->flatMap(fn (string $value): array => preg_split('/\R+/u', trim($value)) ?: [])
            ->map(fn (string $line): string => trim($line, " \t\n\r\0\x0B•-*"))
            ->filter()
            ->map(fn (string $line): array => $this->entry($line))
            ->unique(fn (array $entry): string => Str::lower(($entry['url'] ?? '').'|'.$entry['label']))
            ->values();
    }

    /** @return array{label: string, url: ?string} */
    private function entry(string $line): array
    {
        if (preg_match('~https?://[^\s<>]+~iu', $line, $match) === 1) {
            $url = rtrim($match[0], '.,;:)\]');
            $label = trim(str_replace($match[0], '', $line), " \t\n\r\0\x0B-–—:()[]");

            return [
                'label' => $label !== '' ? $label : $url,
                'url' => filter_var($url, FILTER_VALIDATE_URL) ? $url : null,
            ];
        }

        if (preg_match('~(?:https?://(?:dx\.)?doi\.org/)?(10\.\d{4,9}/[-._;()/:A-Z0-9]+)~iu', $line, $match) === 1) {
            $doi = rtrim($match[1], '.,;:)\]');
            $label = trim(str_replace($match[0], '', $line), " \t\n\r\0\x0B-–—:()[]");

            return [
                'label' => $label !== '' ? $label : 'DOI '.$doi,
                'url' => 'https://doi.org/'.$doi,
            ];
        }

        return ['label' => $line, 'url' => null];
    }
}
