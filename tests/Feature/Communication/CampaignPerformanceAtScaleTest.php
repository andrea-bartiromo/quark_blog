<?php

namespace Tests\Feature\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderProfile;
use App\Models\User;
use App\Services\Communication\CampaignDryRunService;
use App\Services\Communication\CampaignPreflightService;
use App\Services\Communication\RecordingEmailProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * N2.12 — budget di query/performance per le funzionalità introdotte in
 * QUESTA missione: ricerca destinatari (N2.1), preflight (N2.8), dry-run
 * (N2.9). Lo scale-test di RecipientSnapshotService::prepare() a 10k
 * esiste già in RecipientSnapshotRaceAndScaleTest.php dalla missione
 * precedente ("scala 10k" era già DONE) — non ripetuto qui.
 *
 * Seeding con INSERT bulk diretti (non factory/Eloquent in loop): a 10k
 * righe la differenza è tra secondi e minuti, stesso principio già
 * stabilito in RecipientSnapshotRaceAndScaleTest.
 */
class CampaignPerformanceAtScaleTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function seedConfirmedSubscribers(int $count): void
    {
        collect(range(1, $count))->chunk(1000)->each(function ($chunk) {
            DB::table('comm_subscribers')->insert(
                $chunk->map(fn ($i) => [
                    'email' => 'perf-sub-'.Str::random(10).'@example.com',
                    'status' => 'confirmed',
                    'unsubscribe_token' => Str::random(32),
                    'confirmed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all()
            );
        });
    }

    private function draftCampaign(): CommunicationCampaign
    {
        return CommunicationCampaign::factory()->draft()->create([
            'sender_profile_id' => CommunicationSenderProfile::factory()->create()->id,
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);
    }

    /**
     * Riga 'queued' in comm_sends per OGNI iscritto confermato
     * attualmente in DB, per la campagna data — bulk insert diretto,
     * non RecipientSnapshotService (già misurato a scala altrove).
     */
    /**
     * insertOrIgnore, non insert: questo helper viene chiamato due volte
     * sulla STESSA campagna in alcuni test (dopo aver aggiunto altri
     * iscritti confermati), e i primi non devono generare una violazione
     * del vincolo unique(campaign_id, subscriber_id) già coperto.
     */
    private function queueAllConfirmedFor(CommunicationCampaign $campaign): void
    {
        DB::table('comm_subscribers')->where('status', 'confirmed')
            ->orderBy('id')
            ->pluck('id')
            ->chunk(1000)
            ->each(function ($ids) use ($campaign) {
                DB::table('comm_sends')->insertOrIgnore(
                    $ids->map(fn ($id) => [
                        'uuid' => (string) Str::uuid(),
                        'campaign_id' => $campaign->id,
                        'subscriber_id' => $id,
                        'status' => 'queued',
                        'attempts' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            });
    }

    public function test_preview_search_query_count_does_not_grow_between_1k_and_10k_subscribers(): void
    {
        $campaign = $this->draftCampaign();
        $editor = $this->editor();

        $this->seedConfirmedSubscribers(1000);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($editor)
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q=perf-sub-');
        $queryCountAt1k = count(DB::getQueryLog());
        DB::disableQueryLog();
        $response->assertOk();

        $this->seedConfirmedSubscribers(9000);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($editor)
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q=perf-sub-');
        $queryCountAt10k = count(DB::getQueryLog());
        DB::disableQueryLog();
        $response->assertOk();

        $this->assertLessThan(
            20,
            $queryCountAt1k,
            "La ricerca destinatari ha eseguito {$queryCountAt1k} query a 1000 iscritti."
        );
        $this->assertSame(
            $queryCountAt1k,
            $queryCountAt10k,
            "Il conteggio query della ricerca destinatari è cresciuto da {$queryCountAt1k} (1k) a {$queryCountAt10k} (10k): paginate() deve restare a costo costante (una count() + una select limitata), mai un preload dell'intera tabella."
        );
    }

    public function test_preflight_query_count_does_not_grow_between_1k_and_10k_prepared_recipients(): void
    {
        $campaign = $this->draftCampaign();
        $this->seedConfirmedSubscribers(1000);
        $this->queueAllConfirmedFor($campaign);

        DB::flushQueryLog();
        DB::enableQueryLog();
        // Istanza fresca, non riusata: una vera richiesta HTTP indipendente
        // riceve sempre un nuovo model dal route binding — riusare lo
        // stesso oggetto $campaign tra le due misurazioni farebbe
        // beneficiare la seconda della cache di relazione già caricata
        // dalla prima (loadMissing('senderProfile') non ripete la query),
        // falsando il confronto.
        $report1k = app(CampaignPreflightService::class)->assess(CommunicationCampaign::find($campaign->id));
        $queryCountAt1k = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(1000, $report1k->preparedCount);

        $this->seedConfirmedSubscribers(9000);
        $this->queueAllConfirmedFor($campaign);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $report10k = app(CampaignPreflightService::class)->assess(CommunicationCampaign::find($campaign->id));
        $queryCountAt10k = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(10000, $report10k->preparedCount);

        $this->assertLessThan(
            15,
            $queryCountAt1k,
            "Il preflight ha eseguito {$queryCountAt1k} query a 1000 destinatari preparati."
        );
        $this->assertSame(
            $queryCountAt1k,
            $queryCountAt10k,
            "Il conteggio query del preflight è cresciuto da {$queryCountAt1k} (1k) a {$queryCountAt10k} (10k): ogni contatore è un count()/whereDoesntHave() set-based, mai un caricamento riga-per-riga."
        );
    }

    /**
     * Il dry-run (come un invio reale) fa ATOMICAMENTE claim di ogni riga
     * singolarmente per sicurezza sotto concorrenza (vedi il docblock di
     * CampaignDeliveryOrchestrator) — non è, e non deve essere, O(1):
     * cresce con il numero di destinatari. Quello che deve restare vero è
     * che cresca LINEARMENTE (poche query fisse per riga), non
     * quadraticamente (es. un whereIn/full-scan ripetuto ad ogni riga).
     * Verificato confrontando il rapporto di query tra 100 e 1000
     * destinatari: lineare atteso ~10x, un rapporto molto più alto
     * segnalerebbe una regressione. La misura end-to-end completa a 10k
     * (con esiti misti e fallimenti reali) è FASE N2.14, non ripetuta qui.
     */
    public function test_dry_run_query_count_scales_linearly_not_quadratically(): void
    {
        $small = $this->draftCampaign();
        $this->seedConfirmedSubscribers(100);
        $this->queueAllConfirmedFor($small);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(CampaignDryRunService::class)->run($small, new RecordingEmailProvider);
        $queriesAt100 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $large = $this->draftCampaign();
        $this->seedConfirmedSubscribers(900);
        $this->queueAllConfirmedFor($large);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(CampaignDryRunService::class)->run($large, new RecordingEmailProvider);
        $queriesAt1000 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $ratio = $queriesAt1000 / max($queriesAt100, 1);

        $this->assertLessThan(
            15,
            $ratio,
            "Il rapporto di query tra 1000 e 100 destinatari nel dry-run è {$ratio} ({$queriesAt100} -> {$queriesAt1000}): una crescita lineare attesa è ~10x, un rapporto ben più alto suggerisce una regressione quadratica."
        );
    }
}
