<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CollaboratorController;
use App\Http\Controllers\Admin\CommentController as AdminCommentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaFolderController;
use App\Http\Controllers\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Admin\NewsletterPreviewController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\SuggestionController;
use App\Http\Controllers\Admin\TuringController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthorController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NewsletterTrackingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TuringPageController;
use App\Http\Controllers\TuringPublicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Pubbliche ──────────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/notizie', [ArticleController::class, 'index'])->name('notizie');
Route::get('/categoria/{slug}', [ArticleController::class, 'category'])->name('categoria');
Route::get('/articolo/{slug}', [ArticleController::class, 'show'])->name('articolo');
Route::get('/ricerca', [SearchController::class, 'index'])->name('ricerca');
Route::get('/autore/{user}', [AuthorController::class, 'show'])->name('autore');
Route::get('/turing', [TuringPageController::class, 'index'])->name('turing');
Route::get('/turing/enigma', [TuringPublicController::class, 'enigma'])->name('turing.enigma');
Route::get('/turing/ai', [TuringPublicController::class, 'ai'])->name('turing.ai');
Route::get('/turing/legacy', [TuringPublicController::class, 'legacy'])->name('turing.legacy');
Route::get('/turing/computation', [TuringPublicController::class, 'computation'])
    ->name('turing.computation');
Route::get('/turing/intelligence', [TuringPublicController::class, 'intelligence'])
    ->name('turing.intelligence');

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

Route::post('/admin-05dbc57764bf/login', function (Request $r) {
    if (auth()->attempt($r->only('email', 'password'), $r->boolean('remember'))) {
        $r->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    return back()->withErrors(['email' => 'Credenziali non valide.']);
})->middleware(['login.limit', 'login.log'])->name('admin.login.post');

Route::post('/admin/logout', function (Request $r) {
    auth()->logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();

    return redirect()->route('login');
})->name('admin.logout');

// ── Admin protetto ──────────────────────────────────────────────
Route::middleware(['auth', 'editor'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Articoli
    Route::get('/articoli', [AdminArticleController::class, 'index'])->name('articles');
    Route::get('/articoli/nuovo', [AdminArticleController::class, 'create'])->name('articles.create');
    Route::post('/articoli', [AdminArticleController::class, 'store'])->name('articles.store');
    Route::get('/articoli/bozza-rapida', [AdminArticleController::class, 'quickDraft'])->name('articles.quick-draft');
    Route::get('/articoli/{article}/modifica', [AdminArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articoli/{article}', [AdminArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articoli/{article}', [AdminArticleController::class, 'destroy'])->name('articles.destroy');
    Route::patch('/articoli/{article}/verifica', [AdminArticleController::class, 'updateVerification'])->name('articles.verify');
    Route::post('/articoli/{article}/duplica', [AdminArticleController::class, 'duplicate'])->name('articles.duplicate');

    // Categorie
    Route::get('/categorie', [CategoryController::class, 'index'])->name('categories');
    Route::post('/categorie', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categorie/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categorie/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Commenti
    Route::get('/commenti', [AdminCommentController::class, 'index'])->name('comments');
    Route::patch('/commenti/{comment}/approva', [AdminCommentController::class, 'approve'])->name('comments.approve');
    Route::delete('/commenti/{comment}', [AdminCommentController::class, 'destroy'])->name('comments.destroy');

    // Newsletter
    Route::get('/newsletter', [AdminNewsletterController::class, 'index'])->name('newsletter');
    Route::get('/newsletter/export', [AdminNewsletterController::class, 'export'])->name('newsletter.export');
    Route::get('/newsletter/anteprima', [NewsletterPreviewController::class, 'preview'])->name('newsletter.preview');
    Route::post('/newsletter/invia-ora', [NewsletterPreviewController::class, 'send'])->name('newsletter.send-now');
    Route::delete('/newsletter/{newsletter}', [AdminNewsletterController::class, 'destroy'])->name('newsletter.destroy');

    // Suggerimenti AI
    Route::get('/suggerimenti', [SuggestionController::class, 'index'])->name('suggestions');
    Route::post('/suggerimenti/aggiorna', [SuggestionController::class, 'fetch'])->name('suggestions.fetch');
    Route::post('/suggerimenti/{suggestion}/approva', [SuggestionController::class, 'approve'])->name('suggestions.approve');
    Route::post('/suggerimenti/{suggestion}/pubblica', [SuggestionController::class, 'publish'])->name('suggestions.publish');
    Route::delete('/suggerimenti/{suggestion}', [SuggestionController::class, 'destroy'])->name('suggestions.destroy');

    // Speciali editoriali
    Route::get('/turing', [TuringController::class, 'edit'])->name('turing');
    Route::post('/turing', [TuringController::class, 'update'])->name('turing.update');

    // Media
    Route::get('/media', [MediaController::class, 'index'])->name('media');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::post('/media/upload-ajax', [MediaController::class, 'store'])->name('media.upload');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::get('/media/{media}/move-preflight', [MediaController::class, 'movePreflight'])->name('media.move-preflight');
    Route::patch('/media/{media}/move', [MediaController::class, 'move'])->name('media.move');
    Route::post('/media-folders', [MediaFolderController::class, 'store'])->name('media-folders.store');
    Route::delete('/media-folders/{mediaFolder}', [MediaFolderController::class, 'destroy'])->name('media-folders.destroy');

    // Profilo
    Route::get('/profilo', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profilo', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profilo/foto', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::put('/profilo/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Verifica editoriale
    Route::get('/verifica', [VerificationController::class, 'index'])->name('verification');

    // Collaboratori
    Route::get('/collaboratori', [CollaboratorController::class, 'index'])->name('collaborators');
    Route::get('/collaboratori/nuovo', [CollaboratorController::class, 'create'])->name('collaborators.create');
    Route::post('/collaboratori', [CollaboratorController::class, 'store'])->name('collaborators.store');
    Route::get('/collaboratori/{user}/modifica', [CollaboratorController::class, 'edit'])->name('collaborators.edit');
    Route::put('/collaboratori/{user}', [CollaboratorController::class, 'update'])->name('collaborators.update');
    Route::patch('/collaboratori/{user}/reset-password', [CollaboratorController::class, 'resetPassword'])->name('collaborators.reset-password');
    Route::delete('/collaboratori/{user}', [CollaboratorController::class, 'destroy'])->name('collaborators.destroy');

    // Revisione
    Route::get('/revisione', [ReviewController::class, 'index'])->name('review');
    Route::patch('/revisione/{article}/approva', [ReviewController::class, 'approve'])->name('review.approve');
    Route::patch('/revisione/{article}/rifiuta', [ReviewController::class, 'reject'])->name('review.reject');

    // Statistiche e log
    Route::get('/stats', [StatsController::class, 'index'])->name('stats');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity');
    Route::get('/stats/charts', [StatsController::class, 'charts'])->name('stats.charts');

    // Pubblicità
    Route::get('/ads', [AdController::class, 'index'])->name('ads');
    Route::post('/ads', [AdController::class, 'store'])->name('ads.store');
    Route::put('/ads/{ad}', [AdController::class, 'update'])->name('ads.update');
    Route::patch('/ads/{ad}/toggle', [AdController::class, 'toggle'])->name('ads.toggle');
    Route::delete('/ads/{ad}', [AdController::class, 'destroy'])->name('ads.destroy');
});

// ── Login redazione (collaboratori) ───────────────────────────
Route::get('/redazione/login', fn () => abort(404));

Route::get('/redazione-05dbc57764bf/login', function () {
    if (auth()->check()) {
        if (auth()->user()->isEditor()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('redazione.dashboard');
    }

    return view('redazione.login');
})->name('redazione.login');

Route::post('/redazione/login', fn () => abort(404));

Route::post('/redazione-05dbc57764bf/login', function (Request $r) {
    if (auth()->attempt($r->only('email', 'password'), $r->boolean('remember'))) {
        $r->session()->regenerate();
        if (auth()->user()->isEditor()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('redazione.dashboard');
    }

    return back()->withErrors(['email' => 'Credenziali non valide.']);
})->middleware(['login.limit', 'login.log'])->name('redazione.login.post');

// ── Dashboard collaboratori ────────────────────────────────────
Route::middleware(['auth', 'redazione'])->prefix('redazione')->name('redazione.')->group(function () {
    Route::get('/', [App\Http\Controllers\Redazione\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/articoli', [App\Http\Controllers\Redazione\ArticleController::class, 'index'])->name('articles');
    Route::get('/articoli/nuovo', [App\Http\Controllers\Redazione\ArticleController::class, 'create'])->name('articles.create');
    Route::post('/articoli', [App\Http\Controllers\Redazione\ArticleController::class, 'store'])->name('articles.store');
    Route::get('/articoli/{article}/modifica', [App\Http\Controllers\Redazione\ArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articoli/{article}', [App\Http\Controllers\Redazione\ArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articoli/{article}', [App\Http\Controllers\Redazione\ArticleController::class, 'destroy'])->name('articles.destroy');

    Route::get('/profilo', [App\Http\Controllers\Redazione\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profilo', [App\Http\Controllers\Redazione\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profilo/foto', [App\Http\Controllers\Redazione\ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::put('/profilo/password', [App\Http\Controllers\Redazione\ProfileController::class, 'updatePassword'])->name('profile.password');
});

// ── Pagine statiche ────────────────────────────────────────────
Route::get('/la-redazione', fn () => view('redazione'))->name('redazione');
Route::get('/chi-siamo', fn () => view('chi-siamo'))->name('chi-siamo');
Route::get('/pubblicita', fn () => view('pubblicita'))->name('pubblicita');
Route::get('/contatti', fn () => view('contatti'))->name('contatti');
Route::get('/privacy', fn () => view('privacy'))->name('privacy');
Route::get('/cookie', fn () => view('cookie'))->name('cookie');
Route::get('/termini', fn () => view('termini'))->name('termini');
Route::get('/rettifiche', fn () => view('rettifiche'))->name('rettifiche');

Route::post('/contatti', [ContactController::class, 'send'])
    ->middleware('throttle:3,1')
    ->name('contatti.send');

// ── SEO e feed ─────────────────────────────────────────────────
Route::get('/sitemap-index.xml', [SeoController::class, 'sitemapIndex'])->name('sitemap-index');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/feed.xml', [SeoController::class, 'feed'])->name('feed');
Route::get('/news-sitemap.xml', [SeoController::class, 'newsSitemap'])->name('news-sitemap');
