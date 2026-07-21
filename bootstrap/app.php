<?php

use App\Console\Commands\FetchNewsAndGenerateDrafts;
use App\Http\Middleware\EditorMiddleware;
use App\Http\Middleware\LoginRateLimiter;
use App\Http\Middleware\LogLoginAttempts;
use App\Http\Middleware\RedazioneMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        FetchNewsAndGenerateDrafts::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'redazione' => RedazioneMiddleware::class,
            'editor' => EditorMiddleware::class,
            'login.limit' => LoginRateLimiter::class,
            'login.log' => LogLoginAttempts::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (HttpException $e, $request) {
            $code = $e->getStatusCode();
            $viewMap = [404 => 'errors.404', 403 => 'errors.403', 500 => 'errors.500'];
            if (isset($viewMap[$code]) && view()->exists($viewMap[$code])) {
                return response()->view($viewMap[$code], ['exception' => $e], $code);
            }
        });
    })->create();
