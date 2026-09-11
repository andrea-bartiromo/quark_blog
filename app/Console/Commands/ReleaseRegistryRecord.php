<?php

namespace App\Console\Commands;

use App\Services\Deploy\ReleaseRegistry;
use Illuminate\Console\Command;
use Throwable;

/**
 * Prompt 7 (programma 100-prompt Kairus): pensato per essere richiamato
 * da deploy.sh subito dopo la scrittura di REVISION/DEPLOY_INFO (vedi
 * docs/release-checklist.json, post_gate_actions) — mai un gate: esce
 * sempre con successo, anche quando il registro è configurato ma la
 * scrittura fallisce davvero. Un log storico opzionale non deve mai
 * poter bloccare un rilascio altrimenti già verificato da ogni gate
 * precedente.
 */
class ReleaseRegistryRecord extends Command
{
    protected $signature = 'release:record-registry
        {revision : Revisione Git (SHA) da registrare}
        {--stage=deployed : Fase da registrare (deployed, verified, measured, ...)}
        {--note= : Nota libera opzionale}';

    protected $description = 'Registra un evento di rilascio nel registro append-only (DEPLOY_RELEASE_REGISTRY_PATH). Mai bloccante: no-op se non configurato, solo un avviso se la scrittura fallisce.';

    public function handle(ReleaseRegistry $registry): int
    {
        $revision = (string) $this->argument('revision');

        if (! $registry->isEnabled()) {
            $this->info('DEPLOY_RELEASE_REGISTRY_PATH non è configurato: nessun registro da aggiornare. Non è un fallimento.');

            return self::SUCCESS;
        }

        try {
            $registry->record($revision, (string) $this->option('stage'), $this->option('note'));
            $this->info("Registrato nel release registry: {$revision} ({$this->option('stage')}).");
        } catch (Throwable $exception) {
            // Mai fail-closed: vedi il docblock della classe. Un log
            // storico opzionale non deve mai bloccare un rilascio.
            $this->warn("Impossibile scrivere sul release registry, il rilascio prosegue comunque: {$exception->getMessage()}");
        }

        return self::SUCCESS;
    }
}
