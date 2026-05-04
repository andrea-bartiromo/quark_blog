<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    HomeController,
    ArticleController,
    SearchController,
    CommentController,
    NewsletterController,
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
    VerificationController
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

// ── Commenti pubblici ──────────────────────────────────────────
Route::post('/commenti', [CommentController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('commenti.store');

// ── Login admin ─────────────────────────────────────────────────
Route::get('/admin/login', fn () => view('admin.login'))->name('login');

Route::post('/admin/login', function (\Illuminate\Http\Request $r) {
    if (auth()->attempt($r->only('email', 'password'), $r->boolean('remember'))) {
        $r->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }

    return back()->withErrors(['email' => 'Credenziali non corrette.']);
})->name('admin.login.post');

Route::post('/admin/logout', function (\Illuminate\Http\Request $r) {
    auth()->logout();
    $r->session()->invalidate();
    $r->session()->regenerateToken();

    return redirect()->route('login');
})->name('admin.logout');

// ── Admin protetto ──────────────────────────────────────────────
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Articoli
    Route::get('/articoli', [AdminArticleController::class, 'index'])
        ->name('articles');

    Route::get('/articoli/nuovo', [AdminArticleController::class, 'create'])
        ->name('articles.create');

    Route::post('/articoli', [AdminArticleController::class, 'store'])
        ->name('articles.store');

    Route::get('/articoli/{article}/modifica', [AdminArticleController::class, 'edit'])
        ->name('articles.edit');

    Route::put('/articoli/{article}', [AdminArticleController::class, 'update'])
        ->name('articles.update');

    Route::delete('/articoli/{article}', [AdminArticleController::class, 'destroy'])
        ->name('articles.destroy');

    Route::patch('/articoli/{article}/verifica', [AdminArticleController::class, 'updateVerification'])
        ->name('articles.verify');

    // Commenti
    Route::get('/commenti', [AdminCommentController::class, 'index'])
        ->name('comments');

    Route::patch('/commenti/{comment}/approva', [AdminCommentController::class, 'approve'])
        ->name('comments.approve');

    Route::delete('/commenti/{comment}', [AdminCommentController::class, 'destroy'])
        ->name('comments.destroy');

    // Newsletter
    Route::get('/newsletter', [AdminNewsletterController::class, 'index'])
        ->name('newsletter');

    Route::get('/newsletter/export', [AdminNewsletterController::class, 'export'])
        ->name('newsletter.export');

    Route::delete('/newsletter/{newsletter}', [AdminNewsletterController::class, 'destroy'])
        ->name('newsletter.destroy');

    // Suggerimenti automatici AI
    Route::get('/suggerimenti', [SuggestionController::class, 'index'])
        ->name('suggestions');

    Route::post('/suggerimenti/aggiorna', [SuggestionController::class, 'fetch'])
        ->name('suggestions.fetch');

    Route::post('/suggerimenti/{suggestion}/approva', [SuggestionController::class, 'approve'])
        ->name('suggestions.approve');

    Route::post('/suggerimenti/{suggestion}/pubblica', [SuggestionController::class, 'publish'])
        ->name('suggestions.publish');

    Route::delete('/suggerimenti/{suggestion}', [SuggestionController::class, 'destroy'])
        ->name('suggestions.destroy');

    // Media / Upload immagini
    Route::get('/media', [MediaController::class, 'index'])
        ->name('media');

    Route::post('/media', [MediaController::class, 'store'])
        ->name('media.store');

    Route::post('/media/upload-ajax', [MediaController::class, 'store'])
        ->name('media.upload');

    Route::delete('/media/{media}', [MediaController::class, 'destroy'])
        ->name('media.destroy');

    // Profilo redattore
    Route::get('/profilo', [ProfileController::class, 'edit'])
        ->name('profile');

    Route::put('/profilo', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::post('/profilo/foto', [ProfileController::class, 'updatePhoto'])
        ->name('profile.photo');

    Route::put('/profilo/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password');

    // Verifica editoriale
    Route::get('/verifica', [VerificationController::class, 'index'])
        ->name('verification');
});

// ── Pagine statiche ────────────────────────────────────────────
Route::get('/redazione',  fn () => view('redazione'))->name('redazione');
Route::get('/chi-siamo',  fn () => view('chi-siamo'))->name('chi-siamo');
Route::get('/pubblicita', fn () => view('pubblicita'))->name('pubblicita');
Route::get('/contatti',   fn () => view('contatti'))->name('contatti');
Route::get('/privacy',    fn () => view('privacy'))->name('privacy');
Route::get('/cookie',     fn () => view('cookie'))->name('cookie');
Route::get('/termini',    fn () => view('termini'))->name('termini');
Route::get('/rettifiche', fn () => view('rettifiche'))->name('rettifiche');

Route::post('/contatti', function (\Illuminate\Http\Request $r) {
    if ($r->input('website') !== '') {
        return redirect()->route('contatti');
    }

    $r->validate([
        'nome'      => 'required|max:100',
        'email'     => 'required|email',
        'oggetto'   => 'required',
        'messaggio' => 'required|min:20|max:2000',
        'privacy'   => 'accepted',
    ]);

    return redirect()->route('contatti')->with('contact_sent', true);
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
    $articles = \App\Models\Article::published()->get(['slug', 'category', 'updated_at']);
    $categories = array_keys(config('laboratorio.categories'));
    $base = config('app.url');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $static = [
        ['/', '1.0', 'daily'],
        ['/notizie', '0.9', 'daily'],
        ['/redazione', '0.6', 'monthly'],
        ['/chi-siamo', '0.5', 'monthly'],
        ['/pubblicita', '0.4', 'monthly'],
        ['/contatti', '0.4', 'monthly'],
    ];

    foreach ($static as [$path, $priority, $freq]) {
        $xml .= "  <url>\n";
        $xml .= "    <loc>{$base}{$path}</loc>\n";
        $xml .= "    <changefreq>{$freq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";
        $xml .= "  </url>\n";
    }

    foreach ($categories as $slug) {
        $xml .= "  <url>\n";
        $xml .= "    <loc>{$base}/categoria/{$slug}</loc>\n";
        $xml .= "    <changefreq>daily</changefreq>\n";
        $xml .= "    <priority>0.8</priority>\n";
        $xml .= "  </url>\n";
    }

    foreach ($articles as $article) {
        $lastmod = $article->updated_at->toDateString();

        $xml .= "  <url>\n";
        $xml .= "    <loc>{$base}/articolo/{$article->slug}</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <changefreq>monthly</changefreq>\n";
        $xml .= "    <priority>0.7</priority>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');

// ── Feed RSS ───────────────────────────────────────────────────
Route::get('/feed.xml', function () {
    $articles = \App\Models\Article::published()
        ->with('author')
        ->limit(20)
        ->get();

    $base = config('app.url');
    $siteName = config('laboratorio.name');
    $now = now()->toRfc2822String();

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
    $xml .= '<channel>' . "\n";
    $xml .= "  <title>{$siteName}</title>\n";
    $xml .= "  <link>{$base}</link>\n";
    $xml .= "  <description>Scienza e tecnologia che cambiano l'Italia</description>\n";
    $xml .= "  <language>it-IT</language>\n";
    $xml .= "  <lastBuildDate>{$now}</lastBuildDate>\n";
    $xml .= '  <atom:link href="' . $base . '/feed.xml" rel="self" type="application/rss+xml"/>' . "\n";
    $xml .= "  <image>\n";
    $xml .= "    <url>{$base}/assets/icons/logo.png</url>\n";
    $xml .= "    <title>{$siteName}</title>\n";
    $xml .= "    <link>{$base}</link>\n";
    $xml .= "  </image>\n\n";

    foreach ($articles as $article) {
        $title = htmlspecialchars($article->title, ENT_XML1);
        $excerpt = htmlspecialchars($article->excerpt ?? '', ENT_XML1);
        $url = $base . '/articolo/' . $article->slug;
        $date = $article->published_at->toRfc2822String();
        $author = htmlspecialchars($article->author->name, ENT_XML1);
        $cat = htmlspecialchars(config('laboratorio.categories.' . $article->category, ''), ENT_XML1);

        $xml .= "  <item>\n";
        $xml .= "    <title>{$title}</title>\n";
        $xml .= "    <link>{$url}</link>\n";
        $xml .= "    <guid isPermaLink=\"true\">{$url}</guid>\n";
        $xml .= "    <description>{$excerpt}</description>\n";
        $xml .= "    <pubDate>{$date}</pubDate>\n";
        $xml .= "    <dc:creator>{$author}</dc:creator>\n";
        $xml .= "    <category>{$cat}</category>\n";

        $bodyHtml = htmlspecialchars('<p>' . nl2br(strip_tags($article->body)) . '</p>', ENT_XML1);
        $xml .= "    <content:encoded>{$bodyHtml}</content:encoded>\n";
        $xml .= "  </item>\n";
    }

    $xml .= "</channel>\n</rss>";

    return response($xml, 200)->header('Content-Type', 'application/rss+xml; charset=utf-8');
})->name('feed');

// ── Google News Sitemap ────────────────────────────────────────
Route::get('/news-sitemap.xml', function () {
    $articles = \App\Models\Article::where('status', 'published')
        ->where('published_at', '>=', now()->subDays(2))
        ->orderByDesc('published_at')
        ->limit(1000)
        ->get(['title', 'slug', 'category', 'published_at']);

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . PHP_EOL;
    $xml .= '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . PHP_EOL;

    $cats = config('laboratorio.categories');

    foreach ($articles as $a) {
        $url = route('articolo', $a->slug);
        $title = htmlspecialchars($a->title, ENT_XML1);
        $pubDate = $a->published_at->format('c');
        $genre = htmlspecialchars($cats[$a->category] ?? $a->category, ENT_XML1);

        $xml .= "  <url><loc>{$url}</loc><news:news>";
        $xml .= "<news:publication><news:name>Il Laboratorio</news:name><news:language>it</news:language></news:publication>";
        $xml .= "<news:publication_date>{$pubDate}</news:publication_date>";
        $xml .= "<news:title>{$title}</news:title>";
        $xml .= "<news:genres>{$genre}</news:genres>";
        $xml .= "</news:news></url>" . PHP_EOL;
    }

    $xml .= '</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
})->name('news-sitemap');