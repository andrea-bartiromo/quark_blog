<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\RecipientSnapshotService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prova di concorrenza REALE per RecipientSnapshotService::prepare():
 * due processi PHP separati (non thread simulati) preparano lo STESSO
 * snapshot destinatari per la STESSA campagna nello stesso istante,
 * contro una MariaDB reale.
 *
 * Perché non RefreshDatabase: quel trait avvolge il test in una
 * transazione sulla connessione principale, invisibile ai processi
 * figli (ognuno con una propria connessione dopo DB::purge()) — i dati
 * di setup devono essere davvero COMMITTATI prima della fork. Pulizia
 * manuale in tearDown().
 *
 * Perché non basta il test sequenziale già presente in
 * RecipientSnapshotRaceAndScaleTest (5 run consecutivi): PHPUnit è
 * sincrono a singolo processo — non può mai far collidere due
 * insertOrIgnore() nella stessa finestra di scrittura. Solo una vera
 * sovrapposizione multi-processo dimostra che il vincolo
 * unique(campaign_id, subscriber_id) — non un lock applicativo — è
 * l'unica cosa che impedisce righe duplicate sotto corsa reale.
 *
 * Salta (mai fallisce) se pcntl non è disponibile o se la connessione
 * di test non è davvero MariaDB.
 */
class RecipientSnapshotConcurrencyTest extends TestCase
{
    private ?int $campaignId = null;

    /** @var array<int, int> */
    private array $subscriberIds = [];

    protected function tearDown(): void
    {
        if ($this->campaignId !== null) {
            DB::table('comm_sends')->where('campaign_id', $this->campaignId)->delete();
            DB::table('comm_campaigns')->where('id', $this->campaignId)->delete();
        }

        if ($this->subscriberIds !== []) {
            // eligible_total in prepare() è un conteggio GLOBALE su tutti i
            // confirmed — se non ripuliamo i subscriber creati qui, ogni
            // run successivo (nuovo processo `php artisan test`, stesso
            // database MariaDB condiviso) li ritrova ancora "confirmed" e
            // il conteggio del prossimo test cresce a ogni esecuzione.
            DB::table('comm_subscribers')->whereIn('id', $this->subscriberIds)->delete();
        }

        parent::tearDown();
    }

    public function test_two_concurrent_prepares_on_the_same_campaign_never_duplicate_send_rows(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede una connessione MariaDB reale (SQLite non è prova sufficiente per la concorrenza).');
        }

        $campaign = CommunicationCampaign::factory()->draft()->create();
        $this->campaignId = $campaign->id;

        $subscriberIds = CommunicationSubscriber::factory()->confirmed()->count(50)->create()->pluck('id');
        $this->subscriberIds = $subscriberIds->all();

        $resultsDir = sys_get_temp_dir().'/kairus-recipient-snapshot-race-'.uniqid('', true);
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

                    $freshCampaign = CommunicationCampaign::find($campaign->id);
                    $result = app(RecipientSnapshotService::class)->prepare($freshCampaign);

                    $outcome = 'added:'.$result['added'].',present:'.$result['already_present'];
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

        // Indipendentemente da come i 50 subscriber si sono ripartiti tra
        // i due processi (il vincolo unique decide chi "vince" ogni riga,
        // non l'ordine di schedulazione), il totale finale deve essere
        // esattamente 50 — mai duplicato, mai perso.
        $this->assertSame(50, CommunicationSend::where('campaign_id', $campaign->id)->count());
        $this->assertSame(
            50,
            CommunicationSend::where('campaign_id', $campaign->id)->distinct('subscriber_id')->count('subscriber_id'),
            'Ogni subscriber deve avere esattamente una riga comm_sends per questa campagna.'
        );

        foreach ($subscriberIds as $subscriberId) {
            $this->assertSame(
                1,
                CommunicationSend::where('campaign_id', $campaign->id)->where('subscriber_id', $subscriberId)->count()
            );
        }
    }
}
