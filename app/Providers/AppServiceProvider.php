<?php

namespace App\Providers;

use App\Contracts\DatabaseDumpRunner;
use App\Listeners\CheckApplicationHealth;
use App\Models\Article;
use App\Models\ContentCluster;
use App\Observers\ContentClusterSuggestionObserver;
use App\Services\Backup\ProcessDatabaseDumpRunner;
use Carbon\Carbon;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseDumpRunner::class, ProcessDatabaseDumpRunner::class);
    }

    public function boot(): void
    {
        // Usa il nostro componente Blade per la paginazione
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        // Imposta la locale italiana per le date
        Carbon::setLocale('it');

        Article::observe(ContentClusterSuggestionObserver::class);

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

        // Lo schema forzato per route()/url() vive nel middleware
        // ForceHttpsUrlScheme (bootstrap/app.php), non qui: boot() gira una
        // sola volta all'avvio del processo, quindi leggerebbe config('app.url')
        // una volta sola — sbagliato per un valore che in test/console puo'
        // cambiare dopo il boot, e comunque irrilevante fuori da una
        // richiesta HTTP reale (l'unico contesto in cui vengono generati
        // canonical/sitemap/feed).
    }
}
