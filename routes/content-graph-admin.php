<?php

use App\Http\Controllers\Admin\ConceptController;
use App\Http\Controllers\Admin\ConceptQuestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'editor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/concetti', [ConceptController::class, 'index'])->name('concepts.index');
    Route::get('/concetti/nuovo', [ConceptController::class, 'create'])->name('concepts.create');
    Route::post('/concetti', [ConceptController::class, 'store'])->name('concepts.store');

    Route::get('/concetti/{concept}/modifica', [ConceptController::class, 'edit'])->name('concepts.edit');
    Route::put('/concetti/{concept}', [ConceptController::class, 'update'])->name('concepts.update');
    Route::post('/concetti/{concept}/articoli/{article}', [ConceptController::class, 'linkArticle'])->name('concepts.articles.link');
    Route::delete('/concetti/{concept}/articoli/{article}', [ConceptController::class, 'unlinkArticle'])->name('concepts.articles.unlink');
    Route::post('/concetti/{concept}/unisci/{duplicate}', [ConceptController::class, 'merge'])->name('concepts.merge');

    Route::post('/concetti/{concept}/domande', [ConceptQuestionController::class, 'store'])->name('concepts.questions.store');
    Route::put('/concetti/{concept}/domande/{question}', [ConceptQuestionController::class, 'update'])->name('concepts.questions.update');
});
