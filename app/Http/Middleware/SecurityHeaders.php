<?php
/**
 
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 * @link      https://www.illaboratorio.it
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

        // ── Header sicurezza base ───────────────────────────────
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS solo in produzione
        if (config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // ── CSP COMPLETA (TinyMCE FIX) ──────────────────────────
        $csp = implode('; ', [
            "default-src 'self'",

            // ✅ TinyMCE + CDN script
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.tiny.cloud",

            // ✅ TinyMCE usa CSS esterni
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.tiny.cloud",

            // ✅ Font Google
            "font-src 'self' https://fonts.gstatic.com data:",

            // ✅ immagini anche da editor (blob = upload temporaneo)
            "img-src 'self' data: blob: https:",

            // ✅ richieste ajax/editor
            "connect-src 'self' https://cdn.jsdelivr.net https://cdn.tiny.cloud",

            // ✅ media (video/audio da editor)
            "media-src 'self' blob: https:",

            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}