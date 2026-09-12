<?php

namespace App\Console\Commands;

use App\Services\NewsletterReconfirmationService;
use Illuminate\Console\Command;

/**
 * Alias di compatibilità per la vecchia schedulazione. Non esegue più una
 * pulizia indipendente: passa dal medesimo processore 0/10/20/30/40.
 */
class CleanupExpiredNewsletterPending extends Command
{
    protected $signature = 'newsletter:reconfirmation-cleanup {--dry-run : Mostra chi verrebbe rimosso senza modificare dati}';

    protected $description = 'Compatibilità: applica solo la fase di pulizia sicura del ciclo newsletter';

    public function handle(NewsletterReconfirmationService $service): int
    {
        if (! config('newsletter.reconfirmation.cleanup_enabled')) {
            $this->warn('Pulizia disattivata. Nessuna riga toccata.');

            return self::SUCCESS;
        }

        $result = $service->process((bool) $this->option('dry-run'));

        $this->info($this->option('dry-run')
            ? 'Dry-run: '.$result['deletions'].' pendenti verrebbero rimossi solo dopo il ciclo completo.'
            : $result['deletions'].' pendenti rimossi dopo il ciclo completo.');

        return self::SUCCESS;
    }
}
