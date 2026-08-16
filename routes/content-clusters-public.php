<?php

use App\Http\Controllers\ContentClusterController;
use App\Http\Controllers\ContentClusterSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/percorsi', [ContentClusterController::class, 'index'])->name('percorsi.index');

    // Rotte statiche PRIMA del wildcard /percorsi/{slug} qui sotto: un
    // singolo segmento come /percorsi/conferma altrimenti verrebbe
    // interpretato come slug di un Percorso inesistente (404 sbagliato,
    // mai raggiungendo questo controller) — Laravel risolve le rotte
    // nell'ordine di registrazione, non per specificità.
    Route::get('/percorsi/conferma', [ContentClusterSubscriptionController::class, 'confirm'])
        ->name('percorsi.subscribe.confirm');

    Route::get('/percorsi/disiscrivi/{token}', [ContentClusterSubscriptionController::class, 'unsubscribe'])
        ->name('percorsi.subscribe.unsubscribe');

    Route::get('/percorsi/{slug}', [ContentClusterController::class, 'show'])->name('percorsi.show');

    Route::post('/percorsi/{slug}/avvisami', [ContentClusterSubscriptionController::class, 'subscribe'])
        ->middleware('throttle:5,1')
        ->name('percorsi.subscribe');
});
