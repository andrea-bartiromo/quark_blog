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
    protected $signature = 'newsletter:reconfirmation-cleanup';

    protected $description = 'Rimuove gli iscritti pendenti con un sollecito di riconferma scaduto senza risposta';

    public function handle(NewsletterReconfirmationService $service): int
    {
        $deleted = $service->deleteExpiredPending();

        $this->info($deleted === 0
            ? 'Nessun pendente scaduto da rimuovere.'
            : $deleted.' iscritti pendenti scaduti rimossi.');

        return self::SUCCESS;
    }
}
