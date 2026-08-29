<?php

namespace App\Support;

final class PublicImageDimensions
{
    /** @return array{0: int, 1: int}|null */
    public static function forUrl(?string $url): ?array
    {
        if (blank($url)) {
            return null;
        }

        $trimmed = trim($url);
        $host = parse_url($trimmed, PHP_URL_HOST);
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($host) && ($appHost === null || strcasecmp($host, (string) $appHost) !== 0)) {
            return null;
        }

        $path = parse_url($trimmed, PHP_URL_PATH);

        if (! is_string($path) || $path === '' || str_contains(rawurldecode($path), '..')) {
            return null;
        }

        $candidate = public_path(ltrim(rawurldecode($path), '/'));
        $publicRoot = realpath(public_path());
        $resolved = realpath($candidate);

        if ($publicRoot === false || $resolved === false || ! str_starts_with($resolved, $publicRoot.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $dimensions = @getimagesize($resolved);

        if ($dimensions === false || ($dimensions[0] ?? 0) <= 0 || ($dimensions[1] ?? 0) <= 0) {
            return null;
        }

        return [(int) $dimensions[0], (int) $dimensions[1]];
    }
}
