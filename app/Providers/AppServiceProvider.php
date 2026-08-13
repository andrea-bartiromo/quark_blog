<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\ContentCluster;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Usa il nostro componente Blade per la paginazione
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        // Imposta la locale italiana per le date
        Carbon::setLocale('it');

        // Phase 1A mantiene le route Percorsi isolate dalla grande routes/web.php:
        // sono comunque protette dallo stesso auth+editor contract dell'admin.
        require base_path('routes/content-clusters-admin.php');

        // Relazioni inverse disponibili sull'Article senza introdurre una seconda
        // fonte di verita': il pivot resta article_content_cluster.
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
