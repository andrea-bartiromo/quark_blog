<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSend;
use App\Models\CommunicationSenderProfile;
use App\Models\CommunicationSubscriber;
use App\Services\Communication\CampaignDeliveryOrchestrator;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * N2.14 — prova di concorrenza REALE a scala significativa: più worker
 * fake concorrenti (processi PHP separati, real MariaDB) elaborano la
 * STESSA coda di 2.000 righe contemporaneamente. Complementare a
 * CampaignDeliveryOrchestratorConcurrencyTest (una singola riga contesa
 * da 2 worker) — qui la domanda è "la garanzia regge quando molti
 * worker attraversano l'intera coda insieme, non solo su una riga
 * isolata?" La garanzia stessa (UPDATE atomica per-riga guardata da
 * WHERE status='queued') è invariante rispetto alla scala per
 * costruzione — questo test lo conferma empiricamente su un volume
 * reale invece di darlo per assunto, senza il costo di un run completo
 * a 10.000 con più processi (non necessario: l'invariante è
 * indipendente dal numero di righe).
 */
class Newsletter2EndToEnd10kConcurrencyTest extends TestCase
{
    private const BATCH_SIZE = 2000;

    private const WORKERS = 3;

    private ?int $campaignId = null;

    private ?int $senderProfileId = null;

    private array $subscriberIds = [];

    protected function tearDown(): void
    {
        if ($this->campaignId !== null) {
            DB::table('comm_sends')->where('campaign_id', $this->campaignId)->delete();
            DB::table('comm_campaigns')->where('id', $this->campaignId)->delete();
        }
        if (! empty($this->subscriberIds)) {
            DB::table('comm_subscribers')->whereIn('id', $this->subscriberIds)->delete();
        }
        if ($this->senderProfileId !== null) {
            DB::table('comm_sender_profiles')->where('id', $this->senderProfileId)->delete();
        }

        parent::tearDown();
    }

    public function test_multiple_concurrent_fake_workers_process_a_2000_row_queue_with_zero_duplicates(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl non disponibile in questo ambiente — impossibile provare una vera concorrenza multi-processo.');
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Richiede una connessione MariaDB reale (SQLite non è prova sufficiente per la concorrenza).');
        }

        $senderProfile = CommunicationSenderProfile::factory()->create();
        $this->senderProfileId = $senderProfile->id;

        $campaign = CommunicationCampaign::factory()->create([
            'status' => CommunicationCampaign::STATUS_SENDING,
            'sender_profile_id' => $senderProfile->id,
        ]);
        $this->campaignId = $campaign->id;

        $now = now();
        DB::table('comm_subscribers')->insert(
            collect(range(1, self::BATCH_SIZE))->map(fn ($i) => [
                'email' => 'e2e-concurrency-'.Str::random(12).'@example.com',
                'status' => CommunicationSubscriber::STATUS_CONFIRMED,
                'unsubscribe_token' => Str::random(32),
                'confirmed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
        $this->subscriberIds = DB::table('comm_subscribers')
            ->where('email', 'like', 'e2e-concurrency-%')
            ->pluck('id')->all();

        DB::table('comm_sends')->insert(
            collect($this->subscriberIds)->map(fn ($subscriberId) => [
                'uuid' => (string) Str::uuid(),
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriberId,
                'status' => CommunicationSend::STATUS_QUEUED,
                'attempts' => 0,
                'queued_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );

        $resultsDir = sys_get_temp_dir().'/kairus-e2e-concurrency-'.uniqid('', true);
        mkdir($resultsDir, 0777, true);

        DB::disconnect();

        $pids = [];

        for ($worker = 0; $worker < self::WORKERS; $worker++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('pcntl_fork() non riuscita.');
            }

            if ($pid === 0) {
                $sent = 0;
                $skipped = 0;
                $errors = 0;

                try {
                    DB::purge();
                    DB::reconnect();

                    $provider = new RecordingEmailProvider;
                    $orchestrator = app(CampaignDeliveryOrchestrator::class);

                    // Ogni worker attraversa l'INTERA coda (non una
                    // partizione pre-assegnata) — la contesa reale è
                    // proprio sul FATTO che più worker vedano la stessa
                    // riga 'queued' e provino a reclamarla nello stesso
                    // istante.
                    $sendIds = DB::table('comm_sends')
                        ->where('campaign_id', $campaign->id)
                        ->orderBy('id')
                        ->pluck('id');

                    foreach ($sendIds as $sendId) {
                        $send = CommunicationSend::find($sendId);
                        if (! $send) {
                            continue;
                        }
                        $outcome = $orchestrator->processSend($send, $provider);
                        if ($outcome->outcome === 'sent') {
                            $sent++;
                        } elseif ($outcome->outcome === 'skipped') {
                            $skipped++;
                        }
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    file_put_contents($resultsDir.'/error-'.getmypid(), $e->getMessage());
                }

                file_put_contents($resultsDir.'/'.getmypid(), "{$sent}:{$skipped}:{$errors}");

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        DB::reconnect();

        $outcomeFiles = glob($resultsDir.'/*');
        $totalSent = 0;
        $totalSkipped = 0;
        $errorMessages = [];
        foreach ($outcomeFiles as $path) {
            $basename = basename($path);
            if (str_starts_with($basename, 'error-')) {
                $errorMessages[] = file_get_contents($path);

                continue;
            }
            [$sent, $skipped, $errors] = explode(':', trim(file_get_contents($path)));
            $totalSent += (int) $sent;
            $totalSkipped += (int) $skipped;
        }
        foreach ($outcomeFiles as $path) {
            unlink($path);
        }
        rmdir($resultsDir);

        $this->assertSame([], $errorMessages, 'Nessun worker deve sollevare un errore inatteso: '.implode(' | ', $errorMessages));
        $this->assertSame(self::BATCH_SIZE, $totalSent, 'Ogni riga deve essere consegnata esattamente da UN worker, mai zero né più di uno.');

        // Zero duplicati: nessuna riga rimane in uno stato diverso da
        // 'sent', e attempts=1 per ognuna indipendentemente da quanti
        // worker l'hanno vista come 'queued' nello stesso istante.
        $this->assertSame(
            self::BATCH_SIZE,
            CommunicationSend::where('campaign_id', $campaign->id)->where('status', CommunicationSend::STATUS_SENT)->count()
        );
        $this->assertSame(
            0,
            CommunicationSend::where('campaign_id', $campaign->id)->where('attempts', '!=', 1)->count(),
            'Ogni riga deve avere esattamente 1 tentativo persistito, mai più — un doppio incremento tradirebbe una doppia consegna.'
        );
    }
}
