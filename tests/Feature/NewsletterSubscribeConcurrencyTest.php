<?php

namespace Tests\Feature;

use App\Models\Newsletter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S6 FASE 4 — indagine di concorrenza su Newsletter::subscribe(). Ipotesi
 * iniziale: updateOrCreate() e' un check-then-act (SELECT poi INSERT) e
 * quindi vulnerabile a due iscrizioni concorrenti per la stessa email
 * nuova. Verificata FALSA per questa versione di Laravel: Eloquent Builder
 * ::firstOrCreate() (chiamato da updateOrCreate()) passa da createOrFirst()
 * (vendor/laravel/framework/.../Eloquent/Builder.php), che avvolge il
 * create() in una savepoint quando serve e, su
 * UniqueConstraintViolationException, ri-legge la riga appena scritta
 * dall'altro processo invece di propagare l'eccezione — esattamente la
 * protezione che un fix manuale avrebbe dovuto aggiungere. Un primo
 * tentativo di fix (try/catch attorno a updateOrCreate(), con retry) è
 * stato scritto, poi ABBANDONATO dopo aver verificato che era codice morto
 * per uno scenario che il framework già copre — vedi il report S6 per la
 * cronologia dell'indagine.
 *
 * Questo test è quindi una PROVA POSITIVA (non una riproduzione di un bug)
 * con due processi PHP separati e reali contro MariaDB: entrambi
 * chiamano Newsletter::subscribe() per la STESSA email, mai vista prima,
 * nello stesso istante — a guardia di una regressione futura (es. un
 * refactor che sostituisse updateOrCreate() con un firstOrNew()+save()
 * manuale, perdendo la protezione del framework).
 *
 * Salta (mai fallisce) se pcntl non è disponibile o se la connessione di
 * test non è davvero MariaDB.
 */
class NewsletterSubscribeConcurrencyTest extends TestCase
{
    public function test_two_concurrent_subscriptions_for_the_same_new_email_produce_exactly_one_row(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede una connessione MariaDB reale (SQLite non è prova sufficiente per la concorrenza).');
        }

        $email = 'race-'.uniqid('', true).'@example.test';
        $resultsDir = sys_get_temp_dir().'/kairus-newsletter-subscribe-race-'.uniqid('', true);
        mkdir($resultsDir, 0777, true);
        $goFile = $resultsDir.'/go';

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

                    // Busy-wait sul segnale "go" del genitore: minimizza lo
                    // sfasamento di avviamento tra i due figli.
                    while (! file_exists($goFile)) {
                        // intenzionalmente vuoto
                    }

                    Newsletter::subscribe($email);
                    $outcome = 'ok';
                } catch (\Throwable $e) {
                    $outcome = 'error:'.$e::class.':'.$e->getMessage();
                }

                file_put_contents($resultsDir.'/'.getmypid(), $outcome);

                exit(0);
            }

            $pids[] = $pid;
        }

        // Segnale "go" scritto dopo il fork di entrambi i figli, che sono
        // già in attesa attiva a questo punto: massimizza la sovrapposizione.
        file_put_contents($goFile, '1');

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $outcomeFiles = array_filter(glob($resultsDir.'/*'), fn (string $path) => basename($path) !== 'go');
        $outcomes = array_map(fn (string $path) => trim(file_get_contents($path)), $outcomeFiles);
        foreach (glob($resultsDir.'/*') as $path) {
            unlink($path);
        }
        rmdir($resultsDir);

        $this->assertCount(2, $outcomes, 'Attesi esattamente due esiti, uno per processo figlio: '.implode(' | ', $outcomes));

        foreach ($outcomes as $outcome) {
            $this->assertSame('ok', $outcome, 'Un processo figlio ha sollevato un\'eccezione inattesa durante Newsletter::subscribe() concorrente: '.$outcome);
        }

        $this->assertSame(
            1,
            Newsletter::where('email', $email)->count(),
            'Le due iscrizioni concorrenti per la stessa email devono produrre esattamente UNA riga, mai duplicati.'
        );
    }
}
