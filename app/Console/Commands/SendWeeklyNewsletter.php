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
        // Prompt 116-120 (150-prompt program): controllato per primo,
        // prima di leggere qualunque articolo/iscritto — sia l'invio
        // schedulato (routes/console.php) sia il pulsante "Invia ora"
        // (Admin\NewsletterPreviewController::send()) passano da questo
        // stesso comando, quindi disattivarlo qui li ferma entrambi
        // senza un secondo punto di controllo che potrebbe disallinearsi.
        if (! config('newsletter.send_enabled')) {
            $this->warn('Invio newsletter disattivato (NEWSLETTER_SEND_ENABLED=false). Nessun articolo o iscritto è stato letto.');

            return self::INVALID;
        }

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
            $this->info('Articoli trovati: '.$articles->count());
            $this->info('Iscritti trovati: '.$subscribers->count());

            return 0;
        }

        $queued = 0;
        $weekKey = now()->startOfWeek()->format('Y-m-d');

        foreach ($subscribers as $subscriber) {
            $deliveryKey = $weekKey.':'.$subscriber->id;

            // Dispatch del job asincrono con identità stabile per subscriber/settimana.
            SendNewsletterJob::dispatch($subscriber, $deliveryKey);

            $queued++;
        }

        $this->info("Newsletter aggiunte alla queue: {$queued}");

        return 0;
    }
}
