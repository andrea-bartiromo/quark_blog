<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Cantiere 20 (programma 100-cantieri Kairus). `deploy.sh` esegue ogni
 * gate `deploy:verify-*` (più `deploy:asset-drift`) uno alla volta, ma
 * SOLO durante un rilascio reale contro una release già checked-out —
 * scoprire se produzione è pronta per un deploy richiede oggi o
 * eseguire `deploy.sh` per davvero (con i suoi effetti collaterali:
 * refresh cache, scrittura di REVISION/DEPLOY_INFO), oppure lanciare a
 * mano sei comandi separati e ricordarsi quale sia bloccante e quale
 * solo informativo.
 *
 * Questo comando è di sola lettura e non ha ALCUN effetto collaterale:
 * invoca gli stessi comandi `deploy:verify-*`/`deploy:asset-drift` già
 * esistenti (mai una loro riscrittura — resta un'unica fonte di verità
 * per ciascuna verifica) e ne aggrega l'esito in un unico report,
 * distinguendo esplicitamente quali verifiche sono bloccanti in
 * `deploy.sh` (fail-closed, `|| fail`) da quelle solo informative
 * (`|| true`) — così un operatore o un editor può controllare la
 * prontezza di un rilascio in qualunque momento, senza mai invocare
 * `deploy.sh` stesso.
 */
class DeployReadinessReport extends Command
{
    protected $signature = 'deploy:readiness-report {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Aggrega tutte le verifiche di rilascio esistenti (deploy:verify-*, deploy:asset-drift) in un unico report di sola lettura, senza mai eseguire deploy.sh.';

    /**
     * @var list<array{command: string, label: string, blocking: bool}>
     */
    private const CHECKS = [
        [
            'command' => 'deploy:verify-cache-paths',
            'label' => 'Percorsi cache config sotto questa release',
            'blocking' => true,
        ],
        [
            'command' => 'deploy:verify-scheduled-commands',
            'label' => 'Comandi schedulati realmente registrati da Artisan',
            'blocking' => true,
        ],
        [
            'command' => 'deploy:verify-front-controller',
            'label' => 'Integrità public/.htaccess (front controller)',
            'blocking' => true,
        ],
        [
            'command' => 'deploy:asset-drift',
            'label' => 'Coerenza asset statici tra radice applicativa e radice servita',
            'blocking' => true,
        ],
        [
            'command' => 'deploy:verify-persistent-storage',
            'label' => 'Percorsi persistenti (backup, registro rilasci) fuori dalla release corrente',
            'blocking' => false,
        ],
        [
            'command' => 'deploy:verify-database-backup',
            'label' => 'Backup MariaDB (Backup V2) valido e non stantio',
            'blocking' => false,
        ],
    ];

    public function handle(): int
    {
        $results = array_map(function (array $check): array {
            $buffer = new BufferedOutput;
            $exitCode = Artisan::call($check['command'], [], $buffer);

            return [
                'command' => $check['command'],
                'label' => $check['label'],
                'blocking' => $check['blocking'],
                'ok' => $exitCode === 0,
                'output' => trim($buffer->fetch()),
            ];
        }, self::CHECKS);

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextReport($results);
        }

        $anyFailed = collect($results)->contains(fn (array $r) => ! $r['ok']);
        $anyBlockingFailed = collect($results)->contains(fn (array $r) => $r['blocking'] && ! $r['ok']);

        if (! $anyFailed) {
            return self::SUCCESS;
        }

        return $anyBlockingFailed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<array{command: string, label: string, blocking: bool, ok: bool, output: string}>  $results
     */
    private function renderTextReport(array $results): void
    {
        $this->table(
            ['Verifica', 'Tipo in deploy.sh', 'Esito'],
            array_map(fn (array $r) => [
                $r['label'],
                $r['blocking'] ? 'bloccante (|| fail)' : 'informativo (|| true)',
                $r['ok'] ? 'OK' : 'FALLITO',
            ], $results)
        );

        foreach ($results as $r) {
            if ($r['ok']) {
                continue;
            }

            $this->newLine();
            $this->warn("— {$r['label']} ({$r['command']}) —");
            $this->line($r['output']);
        }

        $this->newLine();

        $anyBlockingFailed = collect($results)->contains(fn (array $r) => $r['blocking'] && ! $r['ok']);
        $anyInformationalFailed = collect($results)->contains(fn (array $r) => ! $r['blocking'] && ! $r['ok']);

        if ($anyBlockingFailed) {
            $this->error('Una o più verifiche BLOCCANTI sono fallite: deploy.sh rifiuterebbe questo rilascio.');

            return;
        }

        if ($anyInformationalFailed) {
            $this->warn('Tutte le verifiche bloccanti sono superate; una o più verifiche informative segnalano un rischio da rivedere (non impediscono il rilascio, ma vanno controllate).');

            return;
        }

        $this->info('Tutte le verifiche sono superate.');
    }
}
