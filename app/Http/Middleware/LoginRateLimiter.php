<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LoginRateLimiter
{
    // Max tentativi falliti prima del blocco
    const MAX_ATTEMPTS = 5;

    // Minuti di blocco dopo MAX_ATTEMPTS
    const DECAY_MINUTES = 15;

    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $key = 'login_attempts:'.sha1($request->ip());
        $attempts = Cache::get($key, 0);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $remaining = Cache::get($key.':expires', self::DECAY_MINUTES);

            Log::warning("Login bloccato per IP {$request->ip()} — troppi tentativi falliti");

            return back()->withErrors([
                'email' => 'Troppi tentativi di accesso. Riprova tra '.self::DECAY_MINUTES.' minuti.',
            ]);
        }

        $response = $next($request);

        // Se il login è fallito (redirect back con errori)
        if ($response->getStatusCode() === 302 &&
            session()->has('errors') &&
            session('errors')->has('email')) {

            $attempts++;
            Cache::put($key, $attempts, now()->addMinutes(self::DECAY_MINUTES));
            Cache::put($key.':expires', self::DECAY_MINUTES, now()->addMinutes(self::DECAY_MINUTES));

            Log::warning("Tentativo di login fallito da IP {$request->ip()} — tentativo {$attempts}/".self::MAX_ATTEMPTS);
        } else {
            // Login riuscito — reset tentativi
            Cache::forget($key);
            Cache::forget($key.':expires');
        }

        return $response;
    }
}
