<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EditorMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->isEditor()) {
            // Collaboratore loggato → manda alla sua dashboard
            return redirect()->route('redazione.dashboard')
                ->with('error', 'Non hai i permessi per accedere al pannello admin.');
        }

        return $next($request);
    }
}
