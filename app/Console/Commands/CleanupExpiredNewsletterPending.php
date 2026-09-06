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
    protected $signature = 'newsletter:reconfirmation-cleanup {--dry-run : Mostra chi verrebbe rimosso, senza eliminare nulla}';

    protected $description = 'Rimuove gli iscritti pendenti con un sollecito di riconferma scaduto senza risposta';

    public function handle(NewsletterReconfirmationService $service): int
    {
        // Prompt 101-105 (150-prompt program): questo comando gira ogni
        // giorno senza supervisione (routes/console.php) contro una
        // tabella senza soft-delete — --dry-run e' l'unico modo di
        // vedere cosa verrebbe rimosso PRIMA che accada, riusando la
        // stessa identica query di eleggibilita' della cancellazione
        // reale (NewsletterReconfirmationService::eligibleForExpiredCleanup()),
        // cosi' l'anteprima non puo' mai mentire su cosa accadrebbe.
        if ($this->option('dry-run')) {
            $eligibleIds = $service->eligibleForExpiredCleanup();

            $this->info($eligibleIds->isEmpty()
                ? 'Dry-run: nessun pendente scaduto da rimuovere.'
                : sprintf(
                    'Dry-run: %d iscritti pendenti scaduti VERREBBERO rimossi (nessuna modifica eseguita). ID: %s',
                    $eligibleIds->count(),
                    $eligibleIds->implode(', ')
                ));

            return self::SUCCESS;
        }

        $deleted = $service->deleteExpiredPending();

        $this->info($deleted === 0
            ? 'Nessun pendente scaduto da rimuovere.'
            : $deleted.' iscritti pendenti scaduti rimossi.');

        return self::SUCCESS;
    }
}
