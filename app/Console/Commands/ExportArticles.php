<?php

/**
 * Kairus — Esportazione articoli pubblicati
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportArticles extends Command
{
    protected $signature = 'articles:export {--base-url= : Base URL da usare per gli link assoluti (default: config(app.url))}';

    protected $description = 'Esporta tutti gli articoli pubblicati in storage/app/exports/articles.csv';

    public function handle(): int
    {
        $baseUrl = rtrim($this->option('base-url') ?: config('app.url'), '/');

        $articles = Article::published()->get(['id', 'title', 'slug', 'category', 'published_at']);

        $directory = storage_path('app/exports');
        File::ensureDirectoryExists($directory);

        $path = $directory.'/articles.csv';
        $handle = fopen($path, 'w');

        fputcsv($handle, ['id', 'title', 'slug', 'url', 'category', 'published_at']);

        foreach ($articles as $article) {
            fputcsv($handle, [
                $article->id,
                $article->title,
                $article->slug,
                $baseUrl.'/articolo/'.$article->slug,
                $article->category,
                $article->published_at?->toIso8601String(),
            ]);
        }

        fclose($handle);

        $this->info("Esportati {$articles->count()} articoli in storage/app/exports/articles.csv");

        return Command::SUCCESS;
    }
}
