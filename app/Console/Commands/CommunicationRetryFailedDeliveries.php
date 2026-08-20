<?php

namespace App\Console\Commands;

use App\Jobs\SendPathContinuationNotification;
use App\Models\CommunicationDelivery;
use App\Services\Communication\CommunicationDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Strumento MANUALE per operatore, mai schedulato — stesso pattern di
 * sicurezza di communication:review-stale-sends (elenca prima, agisce solo
 * su --release-all esplicito). CommunicationDeliveryService::retryFailed()
 * esisteva già (riporta una riga da 'failed' a 'pending') ma non era mai
 * invocata da nessun punto raggiungibile dall'operatore: il fallimento era
 * diagnosticabile via query diretta su communication_deliveries ma non
 * effettivamente ritentabile senza intervento manuale sul DB.
 *
 * retryFailed() da sola riporta solo lo stato della riga a 'pending' — non
 * fa mai ripartire l'invio da sola (vedi il suo stesso docblock): il
 * chiamante deve anche ridispatchare il job giusto, esattamente come fa il
 * trigger di pubblicazione originale (PathContinuationNotifier). Questo
 * comando fa lo stesso, mappando notification_type -> job. Oggi esiste un
 * solo notification_type registrato su questo ledger generico
 * ('path_continuation' — la newsletter usa Cache::add(), non questo
 * ledger, vedi SendNewsletterJob). Un futuro notification_type (es. per
 * Kairus Social Launch) va aggiunto qui esplicitamente: un tipo non
 * mappato viene segnalato e saltato, mai ridispacciato a un job a
 * indovinare.
 */
class CommunicationRetryFailedDeliveries extends Command
{
    protected $signature = 'communication:retry-failed-deliveries
        {--type= : Filtra per notification_type (es. path_continuation)}
        {--release-all : Ri-accoda e ridispaccia tutte le righe trovate (azione esplicita dell\'operatore)}';

    protected $description = "Elenca (e opzionalmente ri-accoda e ridispaccia) le righe communication_deliveries in stato 'failed'";

    /**
     * @var array<string, class-string>
     */
    private const JOB_FOR_NOTIFICATION_TYPE = [
        'path_continuation' => SendPathContinuationNotification::class,
    ];

    public function handle(CommunicationDeliveryService $service): int
    {
        $query = CommunicationDelivery::query()->where('status', CommunicationDelivery::STATUS_FAILED);

        if ($type = $this->option('type')) {
            $query->where('notification_type', $type);
        }

        $failed = $query->orderBy('failed_at')->get();

        if ($failed->isEmpty()) {
            $this->info('Nessuna delivery in stato "failed" trovata.');

            return self::SUCCESS;
        }

        $this->warn("{$failed->count()} delivery in stato 'failed':");

        foreach ($failed as $delivery) {
            $this->line("  #{$delivery->id} — tipo {$delivery->notification_type}, iscritto {$delivery->subscriber_id}, tentativi {$delivery->attempts}, fallita il {$delivery->failed_at}: {$delivery->failure_reason}");
        }

        if (! $this->option('release-all')) {
            $this->line('');
            $this->line('Nessuna azione eseguita. Esegui di nuovo con --release-all per ri-accodarle e ridispacciarle.');

            return self::SUCCESS;
        }

        $retried = 0;
        $skipped = 0;

        foreach ($failed as $delivery) {
            $jobClass = self::JOB_FOR_NOTIFICATION_TYPE[$delivery->notification_type] ?? null;

            if ($jobClass === null) {
                $skipped++;
                $this->warn("  #{$delivery->id}: notification_type '{$delivery->notification_type}' non mappato a nessun job — saltata.");

                Log::warning('communication:retry-failed-deliveries — notification_type non mappato.', [
                    'operation' => 'communication.retry_failed_deliveries',
                    'delivery_id' => $delivery->id,
                    'notification_type' => $delivery->notification_type,
                ]);

                continue;
            }

            if (! $service->retryFailed($delivery)) {
                $skipped++;

                continue;
            }

            $jobClass::dispatch($delivery->id);
            $retried++;

            Log::info('communication:retry-failed-deliveries — delivery ri-accodata.', [
                'operation' => 'communication.retry_failed_deliveries',
                'delivery_id' => $delivery->id,
                'notification_type' => $delivery->notification_type,
            ]);
        }

        $this->info("{$retried}/{$failed->count()} delivery ri-accodate e ridispacciate. {$skipped} saltate.");

        return self::SUCCESS;
    }
}
