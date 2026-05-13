<?php

namespace App\Providers;

use App\Http\Controllers\TuringPageController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TuringServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('/turing', [TuringPageController::class, 'index'])->name('turing');
            Route::view('/turing/enigma', 'turing.enigma')->name('turing.enigma');
            Route::view('/turing/ia', 'turing.ai')->name('turing.ai');
        });
    }
}
