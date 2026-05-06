<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Newsletter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWeeklyNewsletter extends Command
{
    protected $signature = 'newsletter:send {--dry-run : Mostra anteprima senza inviare}';
    protected $description = 'Invia la newsletter settimanale agli iscritti confermati';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Top articoli
        $topRead = Article::published()
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        if ($topRead->count() < 3) {
            $topRead = Article::published()
                ->orderByDesc('views')
                ->limit(3)
                ->get();
        }

        $topReadIds = $topRead->pluck('id')->toArray();

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
            return 0;
        }

        $intro = $this->generateIntro($articles);

        $sent = 0;
        $errors = 0;

        foreach ($subscribers as $subscriber) {
            try {
                $html = $this->buildHtml($articles, $intro, $subscriber);

                Mail::send([], [], function ($message) use ($subscriber, $html) {

                    $unsubUrl = route('newsletter.unsubscribe', [
                        'token' => $subscriber->unsubscribe_token,
                    ]);

                    // PIXEL OPEN TRACKING
                    $trackingPixel = "<img src='" . route('newsletter.open', $subscriber->id) . "' width='1' height='1' style='display:none;'>";

                    $fullHtml = $html . "
                        {$trackingPixel}

                        <div style='border-top:1px solid #e5e7eb;margin-top:2rem;padding-top:1rem;text-align:center;'>
                            <p style='color:#9ca3af;font-size:.72rem;margin:0;line-height:1.6;'>
                                Hai ricevuto questa email perché sei iscritto a Quark.<br>
                                <a href='{$unsubUrl}' style='color:#9ca3af;'>Disiscriviti</a>
                            </p>
                        </div>
                    </div>";

                    $message->to($subscriber->email)
                        ->subject('🧪 Quark — I migliori articoli della settimana')
                        ->html($fullHtml);
                });

                $sent++;

            } catch (\Exception $e) {
                $errors++;
                Log::error("Errore newsletter {$subscriber->email}: " . $e->getMessage());
            }

            usleep(100000);
        }

        $this->info("Inviate: {$sent}");
        if ($errors > 0) $this->warn("Errori: {$errors}");

        return 0;
    }

    private function generateIntro($articles): string
    {
        return "Abbiamo selezionato per te gli articoli più interessanti della settimana. Scopri cosa sta succedendo nel mondo della scienza.";
    }

    private function buildHtml($articles, string $intro = '', ?Newsletter $subscriber = null): string
    {
        $base = config('app.url');
        $cats = config('laboratorio.categories');

        $articlesHtml = '';

        foreach ($articles as $i => $art) {

            // 🔥 QUI IL TRACKING CLICK
            $url = $subscriber
                ? route('newsletter.click', [$subscriber->id, $art->id])
                : $base . '/articolo/' . $art->slug;

            $cat = $cats[$art->category] ?? $art->category;

            $articlesHtml .= "
                <div style='margin-bottom:20px;border:1px solid #eee;padding:15px;border-radius:10px;'>
                    <h3>{$art->title}</h3>
                    <p>{$art->excerpt}</p>
                    <a href='{$url}' style='color:#0d9488;font-weight:bold;'>Leggi</a>
                </div>
            ";
        }

        return "
        <div style='font-family:Arial;max-width:600px;margin:auto;'>

            <h1>Quark</h1>

            <p>{$intro}</p>

            {$articlesHtml}

        ";
    }
}