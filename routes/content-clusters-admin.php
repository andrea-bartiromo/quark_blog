<?php

use App\Http\Controllers\Admin\ContentClusterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'editor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/percorsi', [ContentClusterController::class, 'index'])->name('content-clusters.index');
    Route::get('/percorsi/nuovo', [ContentClusterController::class, 'create'])->name('content-clusters.create');
    Route::post('/percorsi', [ContentClusterController::class, 'store'])->name('content-clusters.store');
    Route::get('/percorsi/{contentCluster}/modifica', [ContentClusterController::class, 'edit'])->name('content-clusters.edit');
    Route::put('/percorsi/{contentCluster}', [ContentClusterController::class, 'update'])->name('content-clusters.update');
});
