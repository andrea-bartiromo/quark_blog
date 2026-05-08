<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\{
    HomeController,
    ArticleController,
    SearchController,
    CommentController,
    NewsletterController,
    NewsletterTrackingController,
    AuthorController
    
};

use App\Http\Controllers\Admin\{
    DashboardController,
    ArticleController as AdminArticleController,
    CommentController as AdminCommentController,
    NewsletterController as AdminNewsletterController,
    SuggestionController,
    MediaController,
    ProfileController,
    VerificationController,
    AdController,
    CategoryController
};

// ── Pubbliche ──────────────────────────────────────────────────
Route::get('/',                 [HomeController::class, 'index'])->name('home');
Route::get('/notizie',          [ArticleController::class, 'index'])->name('notizie');
Route::get('/categoria/{slug}', [ArticleController::class, 'category'])->name('categoria');
Route::get('/articolo/{slug}',  [ArticleController::class, 'show'])->name('articolo');
Route::get('/ricerca',          [SearchController::class, 'index'])->name('ricerca');
Route::get('/autore/{user}',    [AuthorController::class, 'show'])->name('autore');

// ── Newsletter pubblica ────────────────────────────────────────
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->middleware('throttle:5,1')
    ->name('newsletter.subscribe');

Route::get('/newsletter/conferma', [NewsletterController::class, 'confirm'])
    ->name('newsletter.confirm');

Route::get('/newsletter/disiscrivi', [NewsletterController::class, 'unsubscribe'])
    ->name('newsletter.unsubscribe');

Route::get('/newsletter/click/{subscriber}/{article}', [NewsletterTrackingController::class, 'click'])
    ->name('newsletter.click');

Route::get('/newsletter/open/{subscriber}', [NewsletterTrackingController::class, 'open'])
    ->name('newsletter.open');

// ── Commenti pubblici ──────────────────────────────────────────
Route::post('/commenti', [CommentController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('commenti.store');

// ── Login admin (URL segreto) ──────────────────────────────────
Route::get('/admin/login', fn () => abort(404));
Route::post('/admin/login', fn () => abort(404));

Route::get('/admin-05dbc57764bf/login', fn () => view('admin.login'))->name('login');

Route::post('/admin-05dbc57764bf/login', function (\Illuminate\Http\Request $r) {
    if (auth()->attempt($r->only('email', 'password'), $r->boolean('remember'))) {
        $r->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }
    return back()->withErrors(['email' => 'Credenziali non valide.']);
})->middleware(['login.limit', 'login.log'])->name('admin.login.post');

Route::post('/admin/logout', function (\Illuminate\Http\Request $r) {
    auth()->logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();
    return redirect()->route('login');
})->name('admin.logout');

Route::middleware(['auth', 'editor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/articoli', [AdminArticleController::class, 'index'])->name('articles');
    Route::get('/articoli/nuovo', [AdminArticleController::class, 'create'])->name('articles.create');
    Route::post('/articoli', [AdminArticleController::class, 'store'])->name('articles.store');
    Route::get('/articoli/bozza-rapida', [AdminArticleController::class, 'quickDraft'])->name('articles.quick-draft');
    Route::get('/articoli/{article}/modifica', [AdminArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articoli/{article}', [AdminArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articoli/{article}', [AdminArticleController::class, 'destroy'])->name('articles.destroy');
    Route::patch('/articoli/{article}/verifica', [AdminArticleController::class, 'updateVerification'])->name('articles.verify');
    Route::post('/articoli/{article}/duplica', [AdminArticleController::class, 'duplicate'])->name('articles.duplicate');

    Route::get('/categorie', [CategoryController::class, 'index'])->name('categories');
    Route::post('/categorie', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categorie/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categorie/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/commenti', [AdminCommentController::class, 'index'])->name('comments');
    Route::patch('/commenti/{comment}/approva', [AdminCommentController::class, 'approve'])->name('comments.approve');
    Route::delete('/commenti/{comment}', [AdminCommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('/newsletter', [AdminNewsletterController::class, 'index'])->name('newsletter');
    Route::get('/newsletter/export', [AdminNewsletterController::class, 'export'])->name('newsletter.export');
    Route::get('/newsletter/anteprima', [\App\Http\Controllers\Admin\NewsletterPreviewController::class, 'preview'])->name('newsletter.preview');
    Route::post('/newsletter/invia-ora', [\App\Http\Controllers\Admin\NewsletterPreviewController::class, 'send'])->name('newsletter.send-now');
    Route::delete('/newsletter/{newsletter}', [AdminNewsletterController::class, 'destroy'])->name('newsletter.destroy');

    Route::get('/suggerimenti', [SuggestionController::class, 'index'])->name('suggestions');
    Route::post('/suggerimenti/aggiorna', [SuggestionController::class, 'fetch'])->name('suggestions.fetch');
    Route::post('/suggerimenti/{suggestion}/approva', [SuggestionController::class, 'approve'])->name('suggestions.approve');
    Route::post('/suggerimenti/{suggestion}/pubblica', [SuggestionController::class, 'publish'])->name('suggestions.publish');
    Route::delete('/suggerimenti/{suggestion}', [SuggestionController::class, 'destroy'])->name('suggestions.destroy');

    Route::get('/media', [MediaController::class, 'index'])->name('media');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::post('/media/upload-ajax', [MediaController::class, 'store'])->name('media.upload');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    Route::get('/profilo', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profilo', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profilo/foto', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::put('/profilo/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    Route::get('/verifica', [VerificationController::class, 'index'])->name('verification');

    Route::get('/collaboratori', [\App\Http\Controllers\Admin\CollaboratorController::class, 'index'])->name('collaborators');
    Route::get('/collaboratori/nuovo', [\App\Http\Controllers\Admin\CollaboratorController::class, 'create'])->name('collaborators.create');
    Route::post('/collaboratori', [\App\Http\Controllers\Admin\CollaboratorController::class, 'store'])->name('collaborators.store');
    Route::get('/collaboratori/{user}/modifica', [\App\Http\Controllers\Admin\CollaboratorController::class, 'edit'])->name('collaborators.edit');
    Route::put('/collaboratori/{user}', [\App\Http\Controllers\Admin\CollaboratorController::class, 'update'])->name('collaborators.update');
    Route::patch('/collaboratori/{user}/reset-password', [\App\Http\Controllers\Admin\CollaboratorController::class, 'resetPassword'])->name('collaborators.reset-password');
    Route::delete('/collaboratori/{user}', [\App\Http\Controllers\Admin\CollaboratorController::class, 'destroy'])->name('collaborators.destroy');

    Route::get('/revisione', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('review');
    Route::patch('/revisione/{article}/approva', [\App\Http\Controllers\Admin\ReviewController::class, 'approve'])->name('review.approve');
    Route::patch('/revisione/{article}/rifiuta', [\App\Http\Controllers\Admin\ReviewController::class, 'reject'])->name('review.reject');

    Route::get('/stats', [\App\Http\Controllers\Admin\StatsController::class, 'index'])->name('stats');
    Route::get('/activity', [\App\Http\Controllers\Admin\ActivityController::class, 'index'])->name('activity');

    Route::get('/stats/charts', [\App\Http\Controllers\Admin\StatsController::class, 'charts'])->name('stats.charts');

    Route::get('/ads', [AdController::class, 'index'])->name('ads');
    Route::post('/ads', [AdController::class, 'store'])->name('ads.store');
    Route::put('/ads/{ad}', [AdController::class, 'update'])->name('ads.update');
    Route::patch('/ads/{ad}/toggle', [AdController::class, 'toggle'])->name('ads.toggle');
    Route::delete('/ads/{ad}', [AdController::class, 'destroy'])->name('ads.destroy');
});
