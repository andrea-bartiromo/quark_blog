<?php

/**
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://kairus.it
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Header di sicurezza di base
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()'
        );

        // HSTS solo in produzione
        if (config('app.env') === 'production') {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        /*
         * Content Security Policy
         *
         * Include:
         * - TinyMCE
         * - jsDelivr
         * - Google Fonts
         * - Google Analytics 4
         * - Google Tag Manager / Tag Assistant
         */
        $csp = implode('; ', [
            "default-src 'self'",

            "script-src 'self' 'unsafe-inline' "
                . "https://cdn.jsdelivr.net "
                . "https://cdn.tiny.cloud "
                . "https://www.googletagmanager.com",

            "script-src-elem 'self' 'unsafe-inline' "
                . "https://cdn.jsdelivr.net "
                . "https://cdn.tiny.cloud "
                . "https://www.googletagmanager.com",

            "style-src 'self' 'unsafe-inline' "
                . "https://fonts.googleapis.com "
                . "https://cdn.jsdelivr.net "
                . "https://cdn.tiny.cloud",

            "font-src 'self' "
                . "https://fonts.gstatic.com "
                . "data:",

            "img-src 'self' data: blob: https: "
                . "https://www.google-analytics.com "
                . "https://region1.google-analytics.com",

            "connect-src 'self' "
                . "https://cdn.jsdelivr.net "
                . "https://cdn.tiny.cloud "
                . "https://www.googletagmanager.com "
                . "https://www.google-analytics.com "
                . "https://region1.google-analytics.com "
                . "https://analytics.google.com",

            "media-src 'self' blob: https:",

            "frame-src 'self' "
                . "https://www.googletagmanager.com",

            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}