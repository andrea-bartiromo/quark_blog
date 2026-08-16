<?php

use App\Http\Controllers\Admin\ContentClusterController;
use App\Http\Controllers\Admin\ContentClusterSuggestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'editor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/percorsi', [ContentClusterController::class, 'index'])->name('content-clusters.index');
    Route::get('/percorsi/nuovo', [ContentClusterController::class, 'create'])->name('content-clusters.create');
    Route::post('/percorsi', [ContentClusterController::class, 'store'])->name('content-clusters.store');
    Route::get('/percorsi/media-picker', [ContentClusterController::class, 'mediaPicker'])->name('content-clusters.media-picker');

    Route::get('/percorsi/suggerimenti', [ContentClusterSuggestionController::class, 'index'])->name('content-cluster-suggestions.index');
    Route::post('/percorsi/suggerimenti/rigenera', [ContentClusterSuggestionController::class, 'regenerate'])->name('content-cluster-suggestions.regenerate');
    Route::post('/percorsi/suggerimenti/{suggestion}/accetta', [ContentClusterSuggestionController::class, 'accept'])->name('content-cluster-suggestions.accept');
    Route::post('/percorsi/suggerimenti/{suggestion}/rifiuta', [ContentClusterSuggestionController::class, 'reject'])->name('content-cluster-suggestions.reject');

    Route::get('/percorsi/{contentCluster}/modifica', [ContentClusterController::class, 'edit'])->name('content-clusters.edit');
    Route::put('/percorsi/{contentCluster}', [ContentClusterController::class, 'update'])->name('content-clusters.update');
    Route::put('/percorsi/{contentCluster}/articoli', [ContentClusterController::class, 'updateMemberships'])->name('content-clusters.memberships.update');
    Route::post('/percorsi/{contentCluster}/articoli/{article}', [ContentClusterController::class, 'addMembership'])->name('content-clusters.memberships.add');
});
