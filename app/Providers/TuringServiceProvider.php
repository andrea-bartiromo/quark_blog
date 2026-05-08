<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TuringServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::view('/turing', 'turing.index')->name('turing');
        Route::view('/turing/enigma', 'turing.enigma')->name('turing.enigma');
        Route::view('/turing/ia', 'turing.ai')->name('turing.ai');
    }
}
