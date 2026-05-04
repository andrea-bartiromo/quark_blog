<?php

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
        \App\Console\Commands\FetchNewsAndGenerateDrafts::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Security headers su tutte le risposte
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // Rate limiting sui form pubblici
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Usa le nostre view Blade per gli errori HTTP
        $exceptions->render(function (HttpException $e, $request) {
            $code    = $e->getStatusCode();
            $viewMap = [404 => 'errors.404', 403 => 'errors.403', 500 => 'errors.500'];

            if (isset($viewMap[$code]) && view()->exists($viewMap[$code])) {
                return response()->view($viewMap[$code], ['exception' => $e], $code);
            }
        });
    })->create();
