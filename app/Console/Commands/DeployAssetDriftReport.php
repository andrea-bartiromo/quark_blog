<?php

namespace App\Console\Commands;

use App\Services\Deploy\PublicAssetDriftDetector;
use Illuminate\Console\Command;

/**
 * Mission 04/05 (deploy asset drift): comando diagnostico read-only, mai
 * mutante. Confronta i file statici gestiti dalla release
 * (config('deploy.asset_drift_scan_paths')) tra l'albero applicativo e la
 * radice servita configurata (DEPLOY_SERVED_PUBLIC_ROOT). Se non
 * configurato, non è un fallimento: esce con successo senza confrontare
 * nulla, cosi' da non bloccare mai un ambiente (locale, test, CI, o una
 * produzione non ancora configurata) che non ha impostato una radice
 * servita separata.
 *
 * Pensato per essere richiamato da deploy.sh (vedi Mission 05) come gate
 * pre-release: un mismatch fa uscire questo comando con codice diverso da
 * zero, cosi' che deploy.sh possa fallire chiaramente PRIMA di scrivere
 * REVISION.
 */
class DeployAssetDriftReport extends Command
{
    protected $signature = 'deploy:asset-drift';

    protected $description = 'Confronta i file statici di release tra la radice applicativa e la radice pubblica effettivamente servita (DEPLOY_SERVED_PUBLIC_ROOT). Solo lettura, non modifica mai alcun file.';

    public function handle(PublicAssetDriftDetector $detector): int
    {
        $report = $detector->report();

        if (! $report['enabled']) {
            $this->info('DEPLOY_SERVED_PUBLIC_ROOT non è configurato (o risolve alla stessa directory della radice applicativa): nessun confronto da fare. Non è un fallimento.');

            return self::SUCCESS;
        }

        $problems = array_values(array_filter(
            $report['entries'],
            fn (array $entry) => $entry['status'] !== PublicAssetDriftDetector::STATUS_OK
        ));

        if ($problems === []) {
            $this->info(sprintf('Nessuna divergenza: %d file confrontati, tutti coincidenti.', $report['totals']['scanned']));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Divergenza rilevata tra le due document root: %d mismatch, %d mancanti sulla radice servita, %d mancanti sulla radice applicativa (su %d file confrontati).',
            $report['totals'][PublicAssetDriftDetector::STATUS_MISMATCH],
            $report['totals'][PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT],
            $report['totals'][PublicAssetDriftDetector::STATUS_MISSING_ON_APP],
            $report['totals']['scanned'],
        ));

        $this->table(
            ['Percorso', 'Stato', 'SHA-256 app', 'SHA-256 servita'],
            array_map(fn (array $entry) => [
                $entry['path'],
                $entry['status'],
                $entry['app_hash'] ?? '—',
                $entry['served_hash'] ?? '—',
            ], $problems)
        );

        return self::FAILURE;
    }
}
