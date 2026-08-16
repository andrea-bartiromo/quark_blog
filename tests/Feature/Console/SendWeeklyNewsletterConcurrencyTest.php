<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prova di concorrenza REALE per la claim Cache::add() usata da
 * SendNewsletterJob::handle(): due processi PHP separati (non thread
 * simulati) tentano di reclamare, nello stesso istante, la STESSA
 * chiave di consegna settimana+iscritto contro una MariaDB reale.
 *
 * Perché non serve allargare artificialmente la sezione critica (a
 * differenza del test di concorrenza per news:fetch, che usa
 * Cache::lock()->get($callback) per questo): Cache::add() non viene mai
 * rilasciata sul percorso di successo — resta impegnata per l'intero TTL
 * di 14 giorni. Non c'è quindi alcuna finestra in cui il primo processo
 * rilascia "troppo in fretta" permettendo al secondo di reclamare
 * legittimamente: sotto una vera corsa multi-processo, l'INSERT atomico
 * (insertOrIgnore contro la PRIMARY KEY di `cache`) garantisce sempre e
 * solo un vincitore, indipendentemente dall'ordine di schedulazione dei
 * due processi.
 *
 * Salta (mai fallisce) se pcntl non è disponibile o se la connessione di
 * test non è davvero MariaDB.
 */
class SendWeeklyNewsletterConcurrencyTest extends TestCase
{
    private ?string $cacheKey = null;

    protected function tearDown(): void
    {
        if ($this->cacheKey !== null) {
            DB::table('cache')->where('key', $this->cacheKey)->delete();
        }

        parent::tearDown();
    }

    public function test_two_concurrent_processes_racing_the_same_delivery_key_produce_exactly_one_claim(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede una connessione MariaDB reale (SQLite non è prova sufficiente per la concorrenza).');
        }

        $this->cacheKey = 'newsletter:delivery:weekly:race-'.uniqid('', true).':1';
        $cacheKey = $this->cacheKey;

        $resultsDir = sys_get_temp_dir().'/kairus-newsletter-claim-race-'.uniqid('', true);
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

                    $claimed = Cache::add($cacheKey, true, now()->addDays(14));
                    $outcome = $claimed ? 'claimed' : 'rejected';
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

        $claimed = count(array_filter($outcomes, fn (string $o) => $o === 'claimed'));
        $rejected = count(array_filter($outcomes, fn (string $o) => $o === 'rejected'));

        $this->assertSame(1, $claimed, 'Esattamente un processo doveva ottenere la claim: '.implode(', ', $outcomes));
        $this->assertSame(1, $rejected, 'Esattamente un processo doveva essere respinto: '.implode(', ', $outcomes));
    }
}
