<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

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
    Route::get('/newsletter/anteprima', [\App\Http\Controllers\Admin\NewsletterPreviewController::class, 'preview'])->name('newsletter.preview');
    Route::post('/newsletter/invia-ora', [\App\Http\Controllers\Admin\NewsletterPreviewController::class, 'send'])->name('newsletter.send-now');
    Route::delete('/newsletter/{newsletter}', [AdminNewsletterController::class, 'destroy'])->name('newsletter.destroy');

    // Suggerimenti AI
    Route::get('/suggerimenti', [SuggestionController::class, 'index'])->name('suggestions');
    Route::post('/suggerimenti/aggiorna', [SuggestionController::class, 'fetch'])->name('suggestions.fetch');
    Route::post('/suggerimenti/{suggestion}/approva', [SuggestionController::class, 'approve'])->name('suggestions.approve');
    Route::post('/suggerimenti/{suggestion}/pubblica', [SuggestionController::class, 'publish'])->name('suggestions.publish');
    Route::delete('/suggerimenti/{suggestion}', [SuggestionController::class, 'destroy'])->name('suggestions.destroy');

    // Media
    Route::get('/media', [MediaController::class, 'index'])->name('media');
    Route::post('/media', [MediaController::class, 'store'])->name('media.store');
    Route::post('/media/upload-ajax', [MediaController::class, 'store'])->name('media.upload');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

    // Profilo
    Route::get('/profilo', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profilo', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profilo/foto', [ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::put('/profilo/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Verifica editoriale
    Route::get('/verifica', [VerificationController::class, 'index'])->name('verification');

    // Collaboratori
    Route::get('/collaboratori', [\App\Http\Controllers\Admin\CollaboratorController::class, 'index'])->name('collaborators');
    Route::get('/collaboratori/nuovo', [\App\Http\Controllers\Admin\CollaboratorController::class, 'create'])->name('collaborators.create');
    Route::post('/collaboratori', [\App\Http\Controllers\Admin\CollaboratorController::class, 'store'])->name('collaborators.store');
    Route::get('/collaboratori/{user}/modifica', [\App\Http\Controllers\Admin\CollaboratorController::class, 'edit'])->name('collaborators.edit');
    Route::put('/collaboratori/{user}', [\App\Http\Controllers\Admin\CollaboratorController::class, 'update'])->name('collaborators.update');
    Route::patch('/collaboratori/{user}/reset-password', [\App\Http\Controllers\Admin\CollaboratorController::class, 'resetPassword'])->name('collaborators.reset-password');
    Route::delete('/collaboratori/{user}', [\App\Http\Controllers\Admin\CollaboratorController::class, 'destroy'])->name('collaborators.destroy');

    // Revisione
    Route::get('/revisione', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('review');
    Route::patch('/revisione/{article}/approva', [\App\Http\Controllers\Admin\ReviewController::class, 'approve'])->name('review.approve');
    Route::patch('/revisione/{article}/rifiuta', [\App\Http\Controllers\Admin\ReviewController::class, 'reject'])->name('review.reject');

    // Statistiche e log
    Route::get('/stats', [\App\Http\Controllers\Admin\StatsController::class, 'index'])->name('stats');
    Route::get('/activity', [\App\Http\Controllers\Admin\ActivityController::class, 'index'])->name('activity');
    Route::get('/stats/charts', [\App\Http\Controllers\Admin\StatsController::class, 'charts'])->name('stats.charts');

    // Pubblicità
    Route::get('/ads', [AdController::class, 'index'])->name('ads');
    Route::post('/ads', [AdController::class, 'store'])->name('ads.store');
    Route::put('/ads/{ad}', [AdController::class, 'update'])->name('ads.update');
    Route::patch('/ads/{ad}/toggle', [AdController::class, 'toggle'])->name('ads.toggle');
    Route::delete('/ads/{ad}', [AdController::class, 'destroy'])->name('ads.destroy');
});

// ── Login redazione (collaboratori) ───────────────────────────
Route::get('/redazione/login', fn () => abort(404));

Route::get('/redazione-05dbc57764bf/login', function() {
    if (auth()->check()) {
        if (auth()->user()->isEditor()) return redirect()->route('admin.dashboard');
        return redirect()->route('redazione.dashboard');
    }
    return view('redazione.login');
})->name('redazione.login');

Route::post('/redazione/login', fn () => abort(404));

Route::post('/redazione-05dbc57764bf/login', function (\Illuminate\Http\Request $r) {
    if (auth()->attempt($r->only('email', 'password'), $r->boolean('remember'))) {
        $r->session()->regenerate();
        if (auth()->user()->isEditor()) return redirect()->route('admin.dashboard');
        return redirect()->route('redazione.dashboard');
    }
    return back()->withErrors(['email' => 'Credenziali non valide.']);
})->middleware(['login.limit', 'login.log'])->name('redazione.login.post');

// ── Dashboard collaboratori ────────────────────────────────────
Route::middleware(['auth', 'redazione'])->prefix('redazione')->name('redazione.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Redazione\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/articoli', [\App\Http\Controllers\Redazione\ArticleController::class, 'index'])->name('articles');
    Route::get('/articoli/nuovo', [\App\Http\Controllers\Redazione\ArticleController::class, 'create'])->name('articles.create');
    Route::post('/articoli', [\App\Http\Controllers\Redazione\ArticleController::class, 'store'])->name('articles.store');
    Route::get('/articoli/{article}/modifica', [\App\Http\Controllers\Redazione\ArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articoli/{article}', [\App\Http\Controllers\Redazione\ArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articoli/{article}', [\App\Http\Controllers\Redazione\ArticleController::class, 'destroy'])->name('articles.destroy');

    Route::get('/profilo', [\App\Http\Controllers\Redazione\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profilo', [\App\Http\Controllers\Redazione\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profilo/foto', [\App\Http\Controllers\Redazione\ProfileController::class, 'updatePhoto'])->name('profile.photo');
    Route::put('/profilo/password', [\App\Http\Controllers\Redazione\ProfileController::class, 'updatePassword'])->name('profile.password');
});

// ── Pagine statiche ────────────────────────────────────────────
Route::get('/la-redazione', fn () => view('redazione'))->name('redazione');
Route::get('/chi-siamo',    fn () => view('chi-siamo'))->name('chi-siamo');
Route::get('/pubblicita',   fn () => view('pubblicita'))->name('pubblicita');
Route::get('/contatti',     fn () => view('contatti'))->name('contatti');
Route::get('/privacy',      fn () => view('privacy'))->name('privacy');
Route::get('/cookie',       fn () => view('cookie'))->name('cookie');
Route::get('/termini',      fn () => view('termini'))->name('termini');
Route::get('/rettifiche',   fn () => view('rettifiche'))->name('rettifiche');

Route::post('/contatti', function (\Illuminate\Http\Request $r) {
    $data = $r->validate([
        'nome'      => 'required|max:100',
        'email'     => 'required|email|max:150',
        'oggetto'   => 'required|max:120',
        'messaggio' => 'required|min:20|max:2000',
        'privacy'   => 'accepted',
    ]);

    $to = env('CONTACT_TO_ADDRESS', config('mail.from.address'));

    try {
        Mail::raw(
            "Nuovo messaggio dal form contatti di Quark\n\n" .
            "Nome: {$data['nome']}\n" .
            "Email: {$data['email']}\n" .
            "Oggetto: {$data['oggetto']}\n\n" .
            "Messaggio:\n{$data['messaggio']}\n\n" .
            "---\nInviato da: " . url('/contatti'),
            function ($message) use ($data, $to) {
                $message->to($to)
                    ->replyTo($data['email'], $data['nome'])
                    ->subject('[Quark] Nuovo messaggio: '.$data['oggetto']);
            }
        );

        return redirect()->route('contatti', ['sent' => '1']);
    } catch (\Throwable $e) {
        report($e);

        return back()->withErrors([
            'email' => 'Il messaggio non è stato inviato. Errore mail: '.$e->getMessage(),
        ])->withInput();
    }
})->middleware('throttle:3,1')->name('contatti.send');

// ── Sitemap index ──────────────────────────────────────────────
Route::get('/sitemap-index.xml', function () {
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    $xml .= '  <sitemap><loc>' . url('/sitemap.xml') . '</loc></sitemap>' . PHP_EOL;
    $xml .= '  <sitemap><loc>' . url('/news-sitemap.xml') . '</loc></sitemap>' . PHP_EOL;
    $xml .= '</sitemapindex>';
    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap-index');

// ── Sitemap XML ────────────────────────────────────────────────
Route::get('/sitemap.xml', function () {
    $articles   = \App\Models\Article::published()->get(['slug', 'category', 'updated_at']);
    $categories = array_keys(\App\Models\Category::options());
    $base       = config('app.url');
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    foreach ([['/', '1.0', 'daily'],['/notizie', '0.9', 'daily'],['/la-redazione', '0.6', 'monthly'],
              ['/chi-siamo', '0.5', 'monthly'],['/pubblicita', '0.4', 'monthly'],['/contatti', '0.4', 'monthly']] as [$path, $priority, $freq]) {
        $xml .= "  <url><loc>{$base}{$path}</loc><changefreq>{$freq}</changefreq><priority>{$priority}</priority></url>" . PHP_EOL;
    }
    foreach ($categories as $slug) {
        $xml .= "  <url><loc>{$base}/categoria/{$slug}</loc><changefreq>daily</changefreq><priority>0.8</priority></url>" . PHP_EOL;
    }
    foreach ($articles as $article) {
        $xml .= "  <url><loc>{$base}/articolo/{$article->slug}</loc><lastmod>{$article->updated_at->toDateString()}</lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>" . PHP_EOL;
    }
    $xml .= '</urlset>';
    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

// ── Feed RSS ───────────────────────────────────────────────────
Route::get('/feed.xml', function () {
    $articles = \App\Models\Article::published()->with('author')->limit(20)->get();
    $base = config('app.url');
    $siteName = config('laboratorio.name');
    $now = now()->toRfc2822String();
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . PHP_EOL;
    $xml .= "<channel>" . PHP_EOL . "  <title>{$siteName}</title>" . PHP_EOL . "  <link>{$base}</link>" . PHP_EOL;
    $xml .= "  <description>La scienza spiegata come si deve</description>" . PHP_EOL . "  <language>it-IT</language>" . PHP_EOL;
    $xml .= "  <lastBuildDate>{$now}</lastBuildDate>" . PHP_EOL;
    $xml .= '  <atom:link href="' . $base . '/feed.xml" rel="self" type="application/rss+xml"/>' . PHP_EOL;
    $xml .= "  <image><url>{$base}/assets/icons/logo.png</url><title>{$siteName}</title><link>{$base}</link></image>" . PHP_EOL . PHP_EOL;
    foreach ($articles as $article) {
        $title   = htmlspecialchars($article->title, ENT_XML1);
        $excerpt = htmlspecialchars($article->excerpt ?? '', ENT_XML1);
        $url     = $base . '/articolo/' . $article->slug;
        $date    = $article->published_at->toRfc2822String();
        $author  = htmlspecialchars($article->author->name, ENT_XML1);
        $cat     = htmlspecialchars(\App\Models\Category::options(false)[$article->category] ?? $article->category, ENT_XML1);
        $body    = htmlspecialchars('<p>' . nl2br(strip_tags($article->body)) . '</p>', ENT_XML1);
        $xml .= "  <item>" . PHP_EOL . "    <title>{$title}</title>" . PHP_EOL . "    <link>{$url}</link>" . PHP_EOL . "    <guid isPermaLink=\"true\">{$url}</guid>" . PHP_EOL;
        $xml .= "    <description>{$excerpt}</description>" . PHP_EOL . "    <pubDate>{$date}</pubDate>" . PHP_EOL;
        $xml .= "    <dc:creator>{$author}</dc:creator>" . PHP_EOL . "    <category>{$cat}</category>" . PHP_EOL;
        $xml .= "    <content:encoded>{$body}</content:encoded>" . PHP_EOL . "  </item>" . PHP_EOL;
    }
    $xml .= "</channel>\n</rss>";
    return response($xml, 200)->header('Content-Type', 'application/rss+xml; charset=utf-8');
})->name('feed');

// ── Google News Sitemap ────────────────────────────────────────
Route::get('/news-sitemap.xml', function () {
    $articles = \App\Models\Article::where('status', 'published')
        ->where('published_at', '>=', now()->subDays(2))
        ->orderByDesc('published_at')->limit(1000)
        ->get(['title', 'slug', 'category', 'published_at']);
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL;
    $xml .= '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . PHP_EOL;
    $cats = \App\Models\Category::options(false);
    foreach ($articles as $a) {
        $url   = route('articolo', $a->slug);
        $title = htmlspecialchars($a->title, ENT_XML1);
        $pub   = $a->published_at->format('c');
        $genre = htmlspecialchars($cats[$a->category] ?? $a->category, ENT_XML1);
        $xml .= "  <url><loc>{$url}</loc><news:news>";
        $xml .= "<news:publication><news:name>Quark</news:name><news:language>it</news:language></news:publication>";
        $xml .= "<news:publication_date>{$pub}</news:publication_date><news:title>{$title}</news:title>";
        $xml .= "<news:genres>{$genre}</news:genres></news:news></url>" . PHP_EOL;
    }
    $xml .= '</urlset>';
    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('news-sitemap');
