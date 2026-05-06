<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogLoginAttempts
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->isMethod('POST') && str_contains($request->path(), 'login')) {
            $ip      = $request->ip();
            $email   = $request->input('email', 'N/A');
            $success = !session()->has('errors');

            if ($success && auth()->check()) {
                Log::info("Login riuscito — IP: {$ip} | Email: {$email}");
            } elseif ($request->has('email')) {
                Log::warning("Login fallito — IP: {$ip} | Email: {$email}");
            }
        }

        return $response;
    }
}
