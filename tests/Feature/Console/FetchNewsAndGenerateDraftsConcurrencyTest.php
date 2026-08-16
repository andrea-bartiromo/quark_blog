<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prova di concorrenza REALE per la claim atomica per-source_url usata da
 * FetchNewsAndGenerateDrafts::handle(): due processi PHP separati (non
 * thread simulati) tentano di acquisire, nello stesso istante, la STESSA
 * chiave di lock ('news:fetch:item:'.sha1($url), TTL 120s — identica a
 * quella del comando) contro una MariaDB reale.
 *
 * Perché testare il lock direttamente e non l'intero comando: il comando
 * non chiama mai un servizio AI reale in test (requisito esplicito:
 * nessuna chiamata di rete reale), quindi senza una chiamata di rete a
 * riempire la sezione critica, l'intero handle() impiega submillisecondi
 * — troppo poco perché due processi forkati si sovrappongano mai
 * davvero, indipendentemente dal fatto che la claim sia atomica o meno
 * (falso negativo garantito, non una prova). Il test di riferimento per
 * la concorrenza in questo repo (CommunicationDeliveryConcurrencyTest)
 * risolve lo stesso problema allargando la sezione critica con la
 * callback che il chiamante fornisce comunque nel mondo reale (l'invio
 * mail); Cache::lock()->get($callback) offre lo stesso punto di
 * estensione di prima classe per la primitiva di lock, senza toccare il
 * comando in produzione. Questo test prova quindi che la primitiva
 * esatta usata da news:fetch (stessa chiave, stesso store, stesso TTL)
 * è realmente esclusiva sotto contesa multi-processo reale; i test
 * sequenziali in FetchNewsAndGenerateDraftsTest provano che il comando
 * la integra correttamente (claim già tenuta da un altro run ⇒ skip
 * prima della generazione; claim rilasciata dopo un fallimento ⇒ un
 * retry può riprovare).
 *
 * Salta (mai fallisce) se pcntl non è disponibile o se la connessione di
 * test non è davvero MariaDB.
 */
class FetchNewsAndGenerateDraftsConcurrencyTest extends TestCase
{
    private ?string $lockKey = null;

    protected function tearDown(): void
    {
        if ($this->lockKey !== null) {
            DB::table('cache_locks')->where('key', $this->lockKey)->delete();
        }

        parent::tearDown();
    }

    public function test_two_concurrent_processes_racing_the_same_lock_key_produce_exactly_one_holder(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede una connessione MariaDB reale (SQLite non è prova sufficiente per la concorrenza).');
        }

        $this->lockKey = 'news:fetch:item:'.sha1('https://example.test/news/race-'.uniqid('', true));
        $lockKey = $this->lockKey;
        $resultsDir = sys_get_temp_dir().'/kairus-news-fetch-lock-race-'.uniqid('', true);
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

                    $lock = Cache::lock($lockKey, 120);
                    $held = false;

                    $lock->get(function () use (&$held) {
                        $held = true;
                        // Allarga deliberatamente la finestra critica per
                        // massimizzare la reale sovrapposizione tra i due
                        // processi in corsa sulla stessa chiave — lo
                        // stesso ruolo che, nel comando reale, avrebbe la
                        // latenza di una vera chiamata AI.
                        usleep(150000);
                    });

                    $outcome = $held ? 'held' : 'blocked';
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

        $held = count(array_filter($outcomes, fn (string $o) => $o === 'held'));
        $blocked = count(array_filter($outcomes, fn (string $o) => $o === 'blocked'));

        $this->assertSame(1, $held, 'Esattamente un processo doveva ottenere la claim: '.implode(', ', $outcomes));
        $this->assertSame(1, $blocked, 'Esattamente un processo doveva restare escluso dalla claim: '.implode(', ', $outcomes));
    }
}
