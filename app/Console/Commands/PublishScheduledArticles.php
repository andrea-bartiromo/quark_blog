<?php

/**
 * Kairus — Pubblicazione automatica articoli programmati
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 */

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublishScheduledArticles extends Command
{
    protected $signature = 'articles:publish-scheduled';

    protected $description = 'Pubblica automaticamente gli articoli programmati la cui data/ora è stata raggiunta';

    public function handle(): int
    {
        $dueIds = Article::query()
            ->where('status', Article::STATUS_SCHEDULED)
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->pluck('id');

        if ($dueIds->isEmpty()) {
            $this->info('Nessun articolo programmato da pubblicare.');

            return Command::SUCCESS;
        }

        $published = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($dueIds as $id) {
            try {
                $article = DB::transaction(function () use ($id) {
                    // Ricontrollo dentro la transazione con lock: protegge da
                    // doppia pubblicazione in caso di esecuzioni sovrapposte
                    // (es. withoutOverlapping non attivo, run manuale concorrente).
                    $article = Article::query()->whereKey($id)->lockForUpdate()->first();

                    if (
                        ! $article
                        || $article->status !== Article::STATUS_SCHEDULED
                        || $article->published_at === null
                        || $article->published_at->isFuture()
                    ) {
                        return null;
                    }

                    // published_at non viene toccato: resta l'istante
                    // programmato, non l'istante (leggermente successivo) in
                    // cui lo scheduler è effettivamente transitato.
                    $article->status = Article::STATUS_PUBLISHED;
                    $article->save();

                    return $article;
                });

                if ($article === null) {
                    $skipped++;

                    continue;
                }

                $published++;

                // user_id nullo: ActivityLog e la vista admin.activity
                // già mostrano "Sistema" per le azioni senza utente autenticato.
                ActivityLog::record(
                    'Articolo pubblicato automaticamente (programmazione)',
                    'article',
                    $article->id,
                    $article->title
                );

                Log::info('Pubblicazione automatica articolo programmato', [
                    'article_id' => $article->id,
                    'title' => $article->title,
                    'published_at' => $article->published_at->toIso8601String(),
                ]);
            } catch (Throwable $exception) {
                $failed++;
                report($exception);

                Log::error('Errore durante la pubblicazione automatica di un articolo programmato', [
                    'article_id' => $id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Pubblicati: {$published} — Saltati: {$skipped} — Falliti: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
