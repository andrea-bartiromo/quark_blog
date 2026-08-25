<?php

namespace App\Console\Commands;

use App\Services\Deploy\PublicAssetDriftDetector;
use Illuminate\Console\Command;

/**
 * Missione 10 (secondo batch autonomo KAIRUS, Fase B — Deployment
 * Reliability): "Add safe dry-run support to any synchronization/deploy
 * tool handling public assets."
 *
 * docs/DEPLOYMENT.md documenta un gap reale e tuttora aperto: nessuno
 * strumento in questo repository copia davvero i file statici di release
 * dall'albero applicativo alla radice servita — quel passaggio resta
 * un'operazione manuale ed esterna, non documentata, mai verificata. Prima
 * di poter anche solo pensare a uno strumento che scriva su una document
 * root di produzione, serve una modalità sola-lettura che dica esattamente
 * cosa quell'operazione manuale farebbe — questo comando e' esattamente e
 * SOLO quello: non esiste, ne' qui ne' altrove in questo repository, una
 * modalità "apply" che scriva sulla radice servita. Vietato esplicitamente
 * dal mandato di questo batch ("never execute production deployment").
 *
 * Non duplica PublicAssetDriftDetector (Missione 04/05 storica, PR #334):
 * lo stesso identico confronto e' gia' implementato e testato li'. Questo
 * comando e' solo una lettura diversa, orientata al piano di sync invece
 * che alla diagnosi di drift, dello stesso identico report() — stessa
 * garanzia di sola lettura, stesso perimetro (Missione 08).
 */
class DeploySyncPlan extends Command
{
    protected $signature = 'deploy:sync-plan';

    protected $description = 'Piano di sincronizzazione a sola lettura (dry-run) tra la radice applicativa e la radice pubblica servita: cosa verrebbe aggiunto, aggiornato, lasciato invariato o ignorato. Non scrive né sposta mai un file.';

    public function handle(PublicAssetDriftDetector $detector): int
    {
        $report = $detector->report();

        if (! $report['enabled']) {
            $this->info('DEPLOY_SERVED_PUBLIC_ROOT non è configurato (o risolve alla stessa directory della radice applicativa): nessun piano di sincronizzazione da calcolare.');

            return self::SUCCESS;
        }

        $toAdd = $this->entriesWithStatus($report['entries'], PublicAssetDriftDetector::STATUS_MISSING_ON_WEBROOT);
        $toUpdate = $this->entriesWithStatus($report['entries'], PublicAssetDriftDetector::STATUS_MISMATCH);
        $unchanged = $this->entriesWithStatus($report['entries'], PublicAssetDriftDetector::STATUS_OK);
        $onlyOnServed = $this->entriesWithStatus($report['entries'], PublicAssetDriftDetector::STATUS_MISSING_ON_APP);

        $this->line('Piano di sincronizzazione (dry-run — nessun file scritto):');
        $this->newLine();

        $this->line(sprintf('  Da AGGIUNGERE sulla radice servita (%d):', count($toAdd)));
        $this->listPaths($toAdd);

        $this->line(sprintf('  Da AGGIORNARE sulla radice servita (%d):', count($toUpdate)));
        $this->listPaths($toUpdate);

        $this->line(sprintf('  Invariati, nessuna azione (%d):', count($unchanged)));
        $this->listPaths($unchanged);

        $this->line(sprintf('  Presenti solo sulla radice servita, IGNORATI intenzionalmente (%d) — questo strumento sincronizza solo dall\'albero applicativo, non cancella mai nulla sulla radice servita:', count($onlyOnServed)));
        $this->listPaths($onlyOnServed);

        $this->newLine();
        $this->line(sprintf(
            'Perimetro scansionato: %s (config(\'deploy.asset_drift_scan_paths\')).',
            implode(', ', (array) config('deploy.asset_drift_scan_paths', []))
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<array{path:string,status:string,app_hash:?string,served_hash:?string}>  $entries
     * @return list<string>
     */
    private function entriesWithStatus(array $entries, string $status): array
    {
        return array_values(array_map(
            fn (array $entry) => $entry['path'],
            array_filter($entries, fn (array $entry) => $entry['status'] === $status)
        ));
    }

    /**
     * @param  list<string>  $paths
     */
    private function listPaths(array $paths): void
    {
        if ($paths === []) {
            $this->line('    (nessuno)');

            return;
        }

        foreach ($paths as $path) {
            $this->line('    - '.$path);
        }
    }
}
