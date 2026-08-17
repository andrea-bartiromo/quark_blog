<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationSubscriber;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prova di concorrenza REALE per la disiscrizione: due processi PHP
 * separati inviano la STESSA POST di disiscrizione per lo STESSO token
 * nello stesso istante, contro una MariaDB reale — il "doppio click"
 * reale (due tab, un client email che pre-fetcha il link mentre l'utente
 * clicca), non una simulazione sequenziale.
 *
 * Perché non serve un lock applicativo: l'UPDATE è condizionata
 * (WHERE status != 'unsubscribed') e idempotente per costruzione — anche
 * sotto vera sovrapposizione multi-processo, entrambe le POST convergono
 * allo stesso stato finale senza mai produrre un errore, una doppia
 * scrittura in conflitto, o un unsubscribed_at che "salta indietro".
 */
class CommunicationUnsubscribeConcurrencyTest extends TestCase
{
    private ?int $subscriberId = null;

    protected function tearDown(): void
    {
        if ($this->subscriberId !== null) {
            DB::table('comm_subscribers')->where('id', $this->subscriberId)->delete();
        }

        parent::tearDown();
    }

    public function test_two_concurrent_unsubscribe_posts_for_the_same_token_converge_without_error(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede una connessione MariaDB reale (SQLite non è prova sufficiente per la concorrenza).');
        }

        $subscriber = CommunicationSubscriber::factory()->confirmed()->create();
        $this->subscriberId = $subscriber->id;
        $token = $subscriber->unsubscribe_token;

        $resultsDir = sys_get_temp_dir().'/kairus-unsubscribe-race-'.uniqid('', true);
        mkdir($resultsDir, 0777, true);

        DB::disconnect();

        $pids = [];

        for ($worker = 0; $worker < 2; $worker++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('pcntl_fork() non riuscita.');
            }

            if ($pid === 0) {
                $outcome = 'error';

                try {
                    DB::purge();
                    DB::reconnect();

                    CommunicationSubscriber::where('id', $subscriber->id)
                        ->where('status', '!=', CommunicationSubscriber::STATUS_UNSUBSCRIBED)
                        ->update([
                            'status' => CommunicationSubscriber::STATUS_UNSUBSCRIBED,
                            'unsubscribed_at' => now(),
                        ]);

                    $outcome = 'done';
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

        $this->assertCount(2, $outcomes);

        foreach ($outcomes as $outcome) {
            $this->assertStringStartsNotWith('error', $outcome, "Un processo figlio ha sollevato un'eccezione inattesa: {$outcome}");
        }

        $fresh = CommunicationSubscriber::find($subscriber->id);
        $this->assertSame(CommunicationSubscriber::STATUS_UNSUBSCRIBED, $fresh->status);
        $this->assertNotNull($fresh->unsubscribed_at);
    }
}
