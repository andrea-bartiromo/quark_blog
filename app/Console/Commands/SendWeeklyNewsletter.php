<?php

namespace App\Console\Commands;

use App\Jobs\SendNewsletterJob;
use App\Models\Article;
use App\Models\Newsletter;
use Illuminate\Console\Command;

class SendWeeklyNewsletter extends Command
{
    protected $signature = 'newsletter:send {--dry-run : Mostra anteprima senza inviare}';

    protected $description = 'Invia la newsletter settimanale agli iscritti confermati';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Top articoli più letti ultimi 7 giorni
        $topRead = Article::published()
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        // Fallback ai più letti globali
        if ($topRead->count() < 3) {
            $topRead = Article::published()
                ->orderByDesc('views')
                ->limit(3)
                ->get();
        }

        $topReadIds = $topRead->pluck('id')->toArray();

        // Ultimi articoli
        $latest = Article::published()
            ->whereNotIn('id', $topReadIds)
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        $articles = $topRead->merge($latest);

        if ($articles->isEmpty()) {
            $this->error('Nessun articolo disponibile.');
            return 1;
        }

        $subscribers = Newsletter::where('confirmed', true)->get();

        if ($subscribers->isEmpty()) {
            $this->warn('Nessun iscritto.');
            return 0;
        }

        if ($dryRun) {
            $this->warn('-- DRY RUN --');
            $this->info('Articoli trovati: ' . $articles->count());
            $this->info('Iscritti trovati: ' . $subscribers->count());

            return 0;
        }

        $queued = 0;

        foreach ($subscribers as $subscriber) {

            // Dispatch del job asincrono
            SendNewsletterJob::dispatch($subscriber);

            $queued++;
        }

        $this->info("Newsletter aggiunte alla queue: {$queued}");

        return 0;
    }
}