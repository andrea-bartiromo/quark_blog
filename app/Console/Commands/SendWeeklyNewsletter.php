<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Newsletter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendWeeklyNewsletter extends Command
{
    protected $signature   = 'newsletter:send {--dry-run : Mostra anteprima senza inviare}';
    protected $description = 'Invia la newsletter settimanale agli iscritti confermati';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Articoli più letti degli ultimi 7 giorni
        $topRead = Article::published()
            ->where('published_at', '>=', now()->subDays(7))
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        // Se non ci sono abbastanza articoli recenti, prendiamo i più letti in assoluto
        if ($topRead->count() < 3) {
            $topRead = Article::published()
                ->orderByDesc('views')
                ->limit(3)
                ->get();
        }

        // Ultimi 2 pubblicati (non già inclusi nei top)
        $topReadIds = $topRead->pluck('id')->toArray();
        $latest = Article::published()
            ->whereNotIn('id', $topReadIds)
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        // Combina: prima i più letti, poi i più recenti
        $articles = $topRead->merge($latest);

        if ($articles->isEmpty()) {
            $this->error('Nessun articolo disponibile per la newsletter.');
            return 1;
        }

        // Iscritti confermati
        $subscribers = Newsletter::where('confirmed', true)->get();

        if ($subscribers->isEmpty()) {
            $this->warn('Nessun iscritto confermato.');
            return 0;
        }

        $this->info("📧 Newsletter settimanale Quark");
        $this->info("   Articoli: {$articles->count()}");
        $this->info("   Destinatari: {$subscribers->count()}");
        $this->newLine();

        // Mostra articoli selezionati
        foreach ($articles as $i => $art) {
            $tag = $i < 3 ? '🔥 Più letto' : '🆕 Nuovo';
            $this->line("  {$tag}: {$art->title}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('-- DRY RUN: nessuna email inviata --');
            return 0;
        }

        // Generiamo il messaggio introduttivo con AI
        $this->info('🤖 Genero il messaggio introduttivo con AI...');
        $intro = $this->generateIntro($articles);

        // Costruiamo l'HTML della newsletter
        $html = $this->buildHtml($articles, $intro);

        $sent  = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($subscribers->count());
        $bar->start();

        foreach ($subscribers as $subscriber) {
            try {
                Mail::send([], [], function ($message) use ($subscriber, $html) {
                    $unsubUrl = route('newsletter.unsubscribe', [
                        'token' => $subscriber->unsubscribe_token
                    ]);

                    $fullHtml = $html . "
                        <div style='border-top:1px solid #e5e7eb;margin-top:2rem;padding-top:1rem;
                                    text-align:center;'>
                            <p style='color:#9ca3af;font-size:.72rem;margin:0;line-height:1.6;'>
                                Hai ricevuto questa email perché sei iscritto alla newsletter di Quark.<br>
                                <a href='{$unsubUrl}' style='color:#9ca3af;'>Clicca qui per disiscriverti</a>
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
                Log::error("Newsletter non inviata a {$subscriber->email}: " . $e->getMessage());
            }

            $bar->advance();
            // Piccola pausa per non sovraccaricare il server email
            usleep(100000); // 0.1 secondi
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Inviate: {$sent}");
        if ($errors > 0) {
            $this->warn("❌ Errori: {$errors}");
        }

        Log::info("Newsletter settimanale inviata: {$sent} successi, {$errors} errori");

        return 0;
    }

    private function generateIntro($articles): string
    {
        $apiKey = config('services.anthropic.key');

        if (!$apiKey) {
            // Fallback se non c'è la API key
            return "Eccoci con una nuova selezione di articoli scientifici. Questa settimana abbiamo scelto per te i contenuti più interessanti e i più letti di Quark. Buona lettura!";
        }

        // Prepariamo il contesto per l'AI
        $articlesContext = $articles->map(fn($a) =>
            "- {$a->title} ({$a->read_minutes} min, categoria: " . config('laboratorio.categories.'.$a->category) . ")"
        )->join("\n");

        $weekNumber = now()->weekOfYear;
        $month = now()->locale('it')->isoFormat('MMMM');

        $prompt = "Sei il direttore di Quark, un blog italiano di divulgazione scientifica con il motto 'La scienza spiegata come si deve'. Scrivi un breve messaggio introduttivo per la newsletter settimanale (settimana {$weekNumber} di {$month}).

Gli articoli di questa settimana sono:
{$articlesContext}

Il messaggio deve:
- Essere caldo, curioso e appassionato — come un amico che ti consiglia cosa leggere
- Fare riferimento a uno o due temi degli articoli senza spoilerare
- Essere breve (3-4 frasi massimo)
- Concludersi con un invito alla lettura
- NON usare frasi banali tipo 'Eccoci qui' o 'Buona settimana'
- Usare un tono giovane, diretto, italiano autentico

Rispondi solo con il testo del messaggio, senza titoli o etichette.";

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 300,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt]
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return trim($data['content'][0]['text'] ?? '');
            }
        } catch (\Exception $e) {
            \Log::warning('AI intro generation failed: ' . $e->getMessage());
        }

        // Fallback
        return "Questa settimana su Quark abbiamo selezionato i contenuti che più ci hanno appassionato. Dalla fisica alle biotecnologie, passando per lo spazio e la tecnologia — c'è qualcosa per ogni curioso. Buona lettura!";
    }

    private function buildHtml($articles, string $intro = ''): string
    {
        $base = config('app.url');
        $cats = config('laboratorio.categories');

        $articlesHtml = '';
        foreach ($articles as $i => $art) {
            $url      = $base . '/articolo/' . $art->slug;
            $cat      = $cats[$art->category] ?? $art->category;
            $excerpt  = $art->excerpt ? substr($art->excerpt, 0, 150) . '...' : '';
            $cover    = $art->cover_image
                        ? $base . '/assets/img/' . $art->cover_image
                        : $base . '/assets/img/hero-placeholder.svg';
            $isTop    = $i < 3;
            $tag      = $isTop ? '🔥 Più letto' : '🆕 Nuovo';
            $tagColor = $isTop ? '#f97316' : '#0d9488';

            $articlesHtml .= "
                <div style='border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:1rem;'>
                    <img src='{$cover}' alt='" . htmlspecialchars($art->title) . "'
                         style='width:100%;height:180px;object-fit:cover;display:block;'>
                    <div style='padding:1rem 1.25rem;'>
                        <div style='display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;'>
                            <span style='font-size:.65rem;font-weight:700;color:{$tagColor};
                                         text-transform:uppercase;letter-spacing:.06em;'>
                                {$tag}
                            </span>
                            <span style='font-size:.65rem;color:#6b7280;'>· {$cat}</span>
                        </div>
                        <h2 style='font-size:1rem;font-weight:700;color:#111827;margin:0 0 .4rem;
                                   line-height:1.35;'>
                            " . htmlspecialchars($art->title) . "
                        </h2>
                        <p style='font-size:.82rem;color:#6b7280;line-height:1.55;margin:0 0 .85rem;'>
                            {$excerpt}
                        </p>
                        <a href='{$url}'
                           style='display:inline-block;background:#0d9488;color:#fff;
                                  padding:.4rem 1rem;border-radius:6px;text-decoration:none;
                                  font-size:.8rem;font-weight:600;'>
                            Leggi →
                        </a>
                    </div>
                </div>
            ";
        }

        $weekNumber = now()->weekOfYear;
        $year = now()->year;

        return "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;
                        background:#ffffff;padding:0;'>

                {{-- Header --}}
                <div style='background:linear-gradient(135deg,#0d9488,#0f766e);
                            padding:2rem;text-align:center;'>
                    <h1 style='color:#fff;font-size:1.8rem;font-weight:900;margin:0 0 .25rem;'>
                        Quark.
                    </h1>
                    <p style='color:rgba(255,255,255,.8);font-size:.82rem;margin:0;'>
                        La scienza spiegata come si deve
                    </p>
                </div>

                {{-- Intro --}}
                <div style='padding:1.5rem 1.5rem 1rem;'>
                    <h2 style='font-size:1.1rem;color:#111827;margin:0 0 .5rem;font-weight:700;'>
                        🧪 I migliori articoli — settimana {$weekNumber}/{$year}
                    </h2>
                    <p style='font-size:.925rem;color:#374151;line-height:1.7;margin:0 0 1.5rem;
                               font-style:italic;border-left:3px solid #0d9488;padding-left:1rem;'>
                        {$intro}
                    </p>

                    {{-- Articoli --}}
                    {$articlesHtml}

                    {{-- CTA --}}
                    <div style='text-align:center;margin:1.5rem 0;padding:1.5rem;
                                background:#f0fdfa;border-radius:10px;'>
                        <p style='font-size:.875rem;color:#0f766e;margin:0 0 .75rem;font-weight:600;'>
                            Vuoi leggere altri articoli?
                        </p>
                        <a href='{$base}/notizie'
                           style='display:inline-block;background:#0d9488;color:#fff;
                                  padding:.6rem 1.5rem;border-radius:8px;
                                  text-decoration:none;font-weight:700;font-size:.9rem;'>
                            Vai su Quark →
                        </a>
                    </div>
                ";
    }
}