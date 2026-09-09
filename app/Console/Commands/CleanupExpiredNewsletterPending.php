<?php

namespace App\Console\Commands;

use App\Services\NewsletterReconfirmationService;
use Illuminate\Console\Command;

/**
 * Rimuove gli iscritti newsletter pendenti (confirmed=false) a cui è già
 * stato inviato almeno un sollecito di riconferma
 * (NewsletterReconfirmationService::send(), sempre un'azione admin
 * esplicita — mai da questo comando) e il cui ultimo token è scaduto
 * senza risposta. Un pendente mai sollecitato non viene mai toccato qui:
 * la pulizia agisce solo su chi ha già avuto una possibilità di
 * riconfermare e non l'ha colta in tempo.
 */
class CleanupExpiredNewsletterPending extends Command
{
    protected $signature = 'newsletter:reconfirmation-cleanup {--dry-run : Mostra chi verrebbe rimosso senza cancellare nulla}';

    protected $description = 'Rimuove gli iscritti pendenti con un sollecito di riconferma scaduto senza risposta';

    public function handle(NewsletterReconfirmationService $service): int
    {
        // Interruttore di emergenza per la SOLA esecuzione schedulata (vedi
        // config/newsletter.php, reconfirmation.cleanup_enabled): non
        // copre l'azione manuale equivalente dell'editor da
        // /admin/newsletter, che chiama il servizio direttamente e resta
        // sempre disponibile.
        if (! config('newsletter.reconfirmation.cleanup_enabled')) {
            $this->warn('Pulizia disattivata da NEWSLETTER_RECONFIRMATION_CLEANUP_ENABLED=false. Nessuna riga toccata.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $eligibleIds = $service->eligibleForExpiredCleanup();

            $this->info($eligibleIds->isEmpty()
                ? 'Dry-run: nessun pendente scaduto da rimuovere.'
                : 'Dry-run: '.$eligibleIds->count().' iscritti pendenti scaduti verrebbero rimossi (ID: '.$eligibleIds->implode(', ').').');

            return self::SUCCESS;
        }

        $deleted = $service->deleteExpiredPending();

        $this->info($deleted === 0
            ? 'Nessun pendente scaduto da rimuovere.'
            : $deleted.' iscritti pendenti scaduti rimossi.');

        return self::SUCCESS;
    }
}
