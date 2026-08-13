<?php

use App\Http\Controllers\ContentClusterController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/percorsi', [ContentClusterController::class, 'index'])->name('percorsi.index');
    Route::get('/percorsi/{slug}', [ContentClusterController::class, 'show'])->name('percorsi.show');
});
