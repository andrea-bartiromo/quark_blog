<?php

namespace App\Providers;

use App\Contracts\DatabaseDumpRunner;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\ArticleDiscoveryController;
use App\Listeners\CheckApplicationHealth;
use App\Models\Article;
use App\Models\ArticleLinkSuggestion;
use App\Models\Category;
use App\Models\ContentCluster;
use App\Observers\ArticleLinkSuggestionCategoryAffinityObserver;
use App\Observers\ArticleLinkSuggestionQualityObserver;
use App\Observers\ArticleSecondaryCategoryObserver;
use App\Observers\ContentClusterSuggestionObserver;
use App\Services\Backup\ProcessDatabaseDumpRunner;
use Carbon\Carbon;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseDumpRunner::class, ProcessDatabaseDumpRunner::class);
        $this->app->bind(AdminArticleController::class, ArticleDiscoveryController::class);
    }

    public function boot(): void
    {
        // Usa il nostro componente Blade per la paginazione
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        // Imposta la locale italiana per le date
        Carbon::setLocale('it');

        Article::observe(ContentClusterSuggestionObserver::class);
        Article::observe(ArticleSecondaryCategoryObserver::class);
        ArticleLinkSuggestion::observe(ArticleLinkSuggestionCategoryAffinityObserver::class);
        ArticleLinkSuggestion::observe(ArticleLinkSuggestionQualityObserver::class);

        // Estende /up (health check nativo, bootstrap/app.php) da liveness
        // a readiness reale — vedi il docblock di CheckApplicationHealth.
        Event::listen(DiagnosingHealth::class, CheckApplicationHealth::class);

        Article::resolveRelationUsing('contentClusters', fn (Article $article) => $article
            ->belongsToMany(ContentCluster::class, 'article_content_cluster')
            ->withPivot(['position', 'is_primary'])
            ->withTimestamps());

        Article::resolveRelationUsing('primaryContentCluster', fn (Article $article) => $article
            ->belongsToMany(ContentCluster::class, 'article_content_cluster')
            ->withPivot(['position', 'is_primary'])
            ->wherePivot('is_primary', true)
            ->limit(1));

        // Relazione dinamica: `articles.category` resta la categoria
        // principale; la pivot contiene esclusivamente le secondarie.
        // La relazione è disponibile come `$article->secondaryCategories`
        // senza alterare il contratto storico del model Article.
        Article::resolveRelationUsing('secondaryCategories', fn (Article $article) => $article
            ->belongsToMany(Category::class, 'article_category')
            ->withTimestamps());

        // Lo schema forzato per route()/url() vive nel middleware
        // ForceHttpsUrlScheme (bootstrap/app.php), non qui: boot() gira una
        // sola volta all'avvio del processo, quindi leggerebbe config('app.url')
        // una volta sola — sbagliato per un valore che in test/console puo'
        // cambiare dopo il boot, e comunque irrilevante fuori da una
        // richiesta HTTP reale (l'unico contesto in cui vengono generati
        // canonical/sitemap/feed).

        // header/category-bar/sidebar/footer sono inclusi da ogni pagina
        // pubblica tramite layouts/app.blade.php, senza un controller
        // dedicato che possa passare le categorie come fa già
        // HomeController per la home o ArticleController per la pagina
        // articolo — per questi 4 partial, il composer è la fonte unica.
        // Prima leggevano tutti config('laboratorio.categories') (lo
        // snapshot statico congelato al deploy): una categoria creata
        // dall'admin dopo il deploy non compariva in nessuno dei quattro,
        // nonostante fosse già selezionabile per la pubblicazione — la
        // stessa classe di bug già corretta lato Redazione in #253, qui
        // sulla navigazione pubblica.
        //
        // Carichiamo una sola volta per Request anche lo stato `is_active`:
        // la navigazione usa solo categorie attive, mentre i badge di
        // articoli già pubblicati devono continuare a mostrare il nome
        // umano anche se la categoria viene successivamente disattivata.
        // Così manteniamo entrambe le semantiche senza aggiungere una
        // seconda query. Il fallback a config resta identico a
        // Category::options() quando la tabella non è ancora disponibile.
        View::composer(
            [
                'components.header',
                'components.category-bar',
                'components.sidebar',
                'components.footer',
            ],
            function ($view) {
                $request = request();
                $activeCacheKey = 'kairus.category_options';
                $allCacheKey = 'kairus.category_label_options';

                if (! $request->attributes->has($activeCacheKey)) {
                    try {
                        $categories = Category::query()
                            ->ordered()
                            ->get(['name', 'slug', 'is_active']);

                        if ($categories->isNotEmpty()) {
                            $all = $categories->pluck('name', 'slug')->toArray();
                            $active = $categories
                                ->where('is_active', true)
                                ->pluck('name', 'slug')
                                ->toArray();
                        } else {
                            $all = config('laboratorio.categories', []);
                            $active = $all;
                        }
                    } catch (\Throwable $e) {
                        $all = config('laboratorio.categories', []);
                        $active = $all;
                    }

                    $request->attributes->set($activeCacheKey, $active);
                    $request->attributes->set($allCacheKey, $all);
                }

                $view->with('categoryOptions', $request->attributes->get($activeCacheKey));
                $view->with('categoryLabelOptions', $request->attributes->get($allCacheKey));
            }
        );
    }
}
