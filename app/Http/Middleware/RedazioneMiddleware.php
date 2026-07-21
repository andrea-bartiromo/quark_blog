<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedazioneMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return redirect()->route('redazione.login');
        }

        if (! auth()->user()->canAccessRedazione()) {
            abort(403, 'Accesso non autorizzato.');
        }

        return $next($request);
    }
}
