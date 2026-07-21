<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Usa il nostro componente Blade per la paginazione
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        // Imposta la locale italiana per le date
        Carbon::setLocale('it');
    }
}
