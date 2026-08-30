<?php

namespace App\Services\DataExport;

final class PrivacySanitizer
{
    public function text(mixed $value): string
    {
        $text = strip_tags((string) $value);
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[redacted-email]', $text) ?? $text;
        $text = preg_replace('/\b(?:token|secret|password|api[_-]?key)\s*[:=]\s*[^\s;,]+/iu', '[redacted-secret]', $text) ?? $text;

        return $text;
    }
}
