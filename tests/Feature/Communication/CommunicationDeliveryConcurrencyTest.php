<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationDelivery;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CommunicationDeliveryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prova di concorrenza REALE per il claim atomico (Parte H della
 * missione): due processi PHP separati (non thread simulati, non lock
 * applicativi) tentano di reclamare la STESSA riga 'pending' nello stesso
 * istante, contro una MariaDB reale.
 *
 * Perché non RefreshDatabase: quel trait avvolge il test in una
 * transazione DB sulla connessione principale, che i processi figli
 * (ognuno con una propria connessione, dopo DB::purge()) non potrebbero
 * mai vedere — i dati di setup devono essere davvero COMMITTATI prima
 * della fork, altrimenti i figli non troverebbero alcuna riga da
 * reclamare. Pulizia manuale in tearDown().
 *
 * Perché non è sufficiente un test sequenziale a singolo processo (vedi
 * CommunicationDeliveryServiceTest): in PHPUnit, sincrono per natura, la
 * ri-lettura fresh() in cima ad attemptSend() già impedisce di rientrare
 * su una riga non più 'pending' PRIMA ancora di raggiungere la UPDATE
 * guardata — quindi un test sequenziale non dimostra mai che la clausola
 * WHERE status = 'pending' della UPDATE sia realmente necessaria. Solo
 * una vera sovrapposizione multi-processo colpisce quella finestra.
 *
 * Salta (mai fallisce) se pcntl non è disponibile o se la connessione di
 * test non è davvero MariaDB — SQLite non è mai accettato come prova di
 * concorrenza per questo test (comportamento di locking non rappresentativo).
 */
class CommunicationDeliveryConcurrencyTest extends TestCase
{
    private ?CommunicationSubscriber $subscriber = null;

    private ?CommunicationDelivery $delivery = null;

    protected function tearDown(): void
    {
        if ($this->delivery !== null) {
            DB::table('communication_deliveries')->where('id', $this->delivery->id)->delete();
        }
        if ($this->subscriber !== null) {
            DB::table('comm_subscribers')->where('id', $this->subscriber->id)->delete();
        }

        parent::tearDown();
    }

    public function test_two_concurrent_processes_racing_the_same_pending_delivery_produce_exactly_one_winner(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // Questo progetto definisce la connessione 'mariadb' con driver
            // nativo 'mariadb' (config/database.php) — non 'mysql'. Un vero
            // client MySQL-compatibile può comunque presentarsi come 'mysql'
            // a seconda della configurazione, quindi entrambi sono accettati.
            $this->markTestSkipped('Richiede una connessione MariaDB reale (Parte N: SQLite non è prova sufficiente per la concorrenza).');
        }

        $this->subscriber = CommunicationSubscriber::create([
            'email' => 'race-'.uniqid('', true).'@example.com',
            'status' => CommunicationSubscriber::STATUS_CONFIRMED,
            'unsubscribe_token' => bin2hex(random_bytes(16)),
            'confirmed_at' => now(),
        ]);

        $this->delivery = app(CommunicationDeliveryService::class)->registerDelivery(
            'email',
            'concurrency_probe',
            $this->subscriber,
            null,
            'race-'.uniqid('', true)
        );

        $deliveryId = $this->delivery->id;

        $resultsDir = sys_get_temp_dir().'/kairus-delivery-race-'.uniqid('', true);
        mkdir($resultsDir, 0777, true);

        // Connessione principale non più necessaria durante la corsa —
        // rilasciata prima della fork per non condividere lo stesso socket
        // TCP tra padre e figli (comportamento non definito per i client
        // MySQL/MariaDB su fork()).
        DB::disconnect();

        $pids = [];

        for ($worker = 0; $worker < 2; $worker++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('pcntl_fork() non riuscita.');
            }

            if ($pid === 0) {
                // ── Processo figlio: connessione DB indipendente ──
                $outcome = 'error';

                try {
                    DB::purge();
                    DB::reconnect();

                    $service = app(CommunicationDeliveryService::class);
                    $delivery = CommunicationDelivery::find($deliveryId);

                    $won = false;
                    $service->attemptSend($delivery, function () use (&$won) {
                        $won = true;
                        // Allarga deliberatamente la finestra critica per
                        // massimizzare la reale sovrapposizione tra i due
                        // processi in corsa sulla stessa riga.
                        usleep(150000);
                    });

                    $outcome = $won ? 'won' : 'lost';
                } catch (\Throwable $e) {
                    $outcome = 'error:'.$e->getMessage();
                }

                file_put_contents($resultsDir.'/'.getmypid(), $outcome);

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $outcomeFiles = glob($resultsDir.'/*');
        $outcomes = array_map(fn (string $path) => trim(file_get_contents($path)), $outcomeFiles);
        foreach ($outcomeFiles as $path) {
            unlink($path);
        }
        rmdir($resultsDir);

        $this->assertCount(2, $outcomes, 'Attesi esattamente due esiti, uno per processo figlio: '.implode(', ', $outcomes));

        foreach ($outcomes as $outcome) {
            $this->assertStringStartsNotWith('error', $outcome, "Un processo figlio ha sollevato un'eccezione inattesa: {$outcome}");
        }

        $winners = count(array_filter($outcomes, fn (string $o) => $o === 'won'));
        $losers = count(array_filter($outcomes, fn (string $o) => $o === 'lost'));

        $this->assertSame(1, $winners, 'Esattamente un processo doveva vincere il claim atomico: '.implode(', ', $outcomes));
        $this->assertSame(1, $losers, 'Esattamente un processo doveva perdere il claim: '.implode(', ', $outcomes));

        $final = CommunicationDelivery::find($deliveryId);
        $this->assertSame(CommunicationDelivery::STATUS_SENT, $final->status);
        $this->assertSame(1, $final->attempts, 'Solo il claim vincente deve aver incrementato attempts.');
        $this->assertSame(
            1,
            DB::table('communication_deliveries')->where('id', $deliveryId)->count(),
            'Il vincolo unique su delivery_key deve garantire una sola riga logica anche sotto corsa reale.'
        );
    }
}
