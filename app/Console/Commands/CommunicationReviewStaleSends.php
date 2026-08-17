<?php

namespace App\Console\Commands;

use App\Services\Communication\StaleSendRecoveryService;
use Illuminate\Console\Command;

/**
 * Strumento MANUALE per operatore, mai schedulato: elenca le righe
 * comm_sends bloccate in 'sending' oltre la finestra data e, solo su
 * richiesta esplicita (--release-all), le riporta a 'queued' per un
 * futuro retry. Vedi il docblock di StaleSendRecoveryService per il
 * perché nessun rilascio automatico è mai corretto qui.
 */
class CommunicationReviewStaleSends extends Command
{
    protected $signature = 'communication:review-stale-sends
        {--minutes=30 : Soglia in minuti oltre la quale una riga sending è considerata bloccata}
        {--release-all : Riporta a queued tutte le righe trovate (azione esplicita dell\'operatore)}';

    protected $description = "Elenca (e opzionalmente rilascia manualmente) le righe comm_sends bloccate in 'sending'";

    public function handle(StaleSendRecoveryService $service): int
    {
        $minutes = (int) $this->option('minutes');
        $stale = $service->findStale($minutes);

        if ($stale->isEmpty()) {
            $this->info("Nessuna riga bloccata in 'sending' da oltre {$minutes} minuti.");

            return self::SUCCESS;
        }

        $this->warn("{$stale->count()} riga/e bloccata/e in 'sending' da oltre {$minutes} minuti:");

        foreach ($stale as $send) {
            $this->line("  #{$send->id} — campagna {$send->campaign_id}, iscritto {$send->subscriber_id}, da {$send->updated_at}");
        }

        if (! $this->option('release-all')) {
            $this->line('');
            $this->line('Nessuna azione eseguita. Esegui di nuovo con --release-all per riportarle a queued (retry al prossimo run).');

            return self::SUCCESS;
        }

        $released = 0;

        foreach ($stale as $send) {
            if ($service->release($send)) {
                $released++;
            }
        }

        $this->info("{$released}/{$stale->count()} riga/e riportata/e a queued.");

        return self::SUCCESS;
    }
}
