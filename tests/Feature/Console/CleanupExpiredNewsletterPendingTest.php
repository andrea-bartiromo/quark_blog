<?php

namespace Tests\Feature\Console;

use App\Models\Newsletter;
use App\Models\NewsletterReconfirmation;
use App\Models\User;
use App\Services\NewsletterReconfirmationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CleanupExpiredNewsletterPendingTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_deletes_a_pending_subscriber_whose_reconfirmation_token_expired_without_a_reply(): void
    {
        $subscriber = Newsletter::subscribe('expired-pending@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
    }

    public function test_never_deletes_a_pending_subscriber_who_was_never_sent_a_reconfirmation(): void
    {
        $subscriber = Newsletter::subscribe('never-sollicited@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_never_deletes_a_pending_subscriber_whose_reconfirmation_is_still_valid(): void
    {
        $subscriber = Newsletter::subscribe('still-valid@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now(),
            'expires_at' => now()->addDays(6),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_never_deletes_a_confirmed_subscriber_even_with_an_expired_reconfirmation_record(): void
    {
        $subscriber = Newsletter::subscribe('confirmed-with-history@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
            'confirmed_at' => now()->subDays(9),
        ]);
        $subscriber->update(['confirmed' => true, 'token' => null]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id, 'confirmed' => true]);
    }

    public function test_admin_can_trigger_the_same_cleanup_manually(): void
    {
        Mail::fake();

        $subscriber = Newsletter::subscribe('manual-cleanup@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.cleanup'));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
    }

    /**
     * Prompt 171-185 (audit e hardening operativo di #533, già in main):
     * fino a questo hardening non esisteva alcun modo di sospendere questa
     * cancellazione automatica giornaliera senza disabilitare l'intero
     * scheduler Laravel. Analogo a NEWSLETTER_SEND_ENABLED per
     * newsletter:send.
     */
    public function test_kill_switch_disabled_skips_the_cleanup_entirely(): void
    {
        config(['newsletter.reconfirmation.cleanup_enabled' => false]);

        $subscriber = Newsletter::subscribe('kill-switch@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    /**
     * Il kill switch copre solo l'esecuzione automatica schedulata:
     * l'editor deve poter continuare a rimuovere pendenti scaduti su
     * richiesta esplicita anche quando la pulizia automatica è sospesa —
     * è già una decisione umana deliberata, non un'attivazione automatica
     * non presidiata.
     */
    public function test_kill_switch_does_not_affect_the_manual_admin_trigger(): void
    {
        config(['newsletter.reconfirmation.cleanup_enabled' => false]);
        Mail::fake();

        $subscriber = Newsletter::subscribe('kill-switch-manual@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $response = $this->actingAs($this->editor())
            ->post(route('admin.newsletter.reconfirmation.cleanup'));

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
    }

    public function test_dry_run_reports_eligible_subscribers_without_deleting_anything(): void
    {
        $subscriber = Newsletter::subscribe('dry-run@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup', ['--dry-run' => true])
            ->expectsOutputToContain((string) $subscriber->id)
            ->assertExitCode(0);

        $this->assertDatabaseHas('newsletter', ['id' => $subscriber->id]);
    }

    public function test_dry_run_reports_nothing_eligible_when_there_is_nothing_to_remove(): void
    {
        Newsletter::subscribe('dry-run-clean@example.com');

        $this->artisan('newsletter:reconfirmation-cleanup', ['--dry-run' => true])
            ->expectsOutputToContain('nessun pendente scaduto')
            ->assertExitCode(0);
    }

    /**
     * withoutOverlapping() a livello di scheduler protegge da due run
     * schedulate sovrapposte, ma non da un run manuale (admin o comando)
     * che coincide con quello automatico. Prova la seconda linea di difesa
     * concreta: la stessa cancellazione ripetuta due volte di seguito è un
     * no-op sicuro la seconda volta, non un errore né una doppia
     * cancellazione.
     */
    public function test_running_the_cleanup_twice_in_a_row_is_safe_and_idempotent(): void
    {
        $subscriber = Newsletter::subscribe('idempotent@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);
        $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);

        // Seconda esecuzione: nulla di eleggibile è rimasto, deve restare
        // un no-op pulito, mai un errore su una riga già rimossa.
        $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);
    }

    public function test_command_is_registered_on_the_scheduler_with_overlap_protection(): void
    {
        $schedule = app(Schedule::class);
        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains((string) ($event->command ?? ''), 'newsletter:reconfirmation-cleanup'));

        $this->assertNotNull($event);
        $this->assertTrue($event->withoutOverlapping);
    }

    /**
     * Difetto di rilascio riscontrato: dopo installazione, cache e asset
     * gate puliti, `php artisan newsletter:reconfirmation-cleanup
     * --dry-run` falliva con "Command is not defined". Ogni test sopra
     * usa `$this->artisan(...)`, l'helper di test di Laravel — che gira
     * NELLO STESSO processo PHP già bootstrappato da PHPUnit, dove la
     * scoperta dei comandi è già avvenuta con successo: non riproduce e
     * non protegge da un fallimento di registrazione che si manifesta
     * solo all'avvio di un vero processo CLI (`php artisan ...`), che
     * ripete da zero il boot di Composer/bootstrap/app.php. Questo test
     * lancia un vero sottoprocesso invece di richiamare il comando
     * in-process, cosi' la stessa classe di regressione (un comando sotto
     * app/Console/Commands/ che silenziosamente smette di essere
     * scoperto) farebbe fallire la suite, non solo un tentativo di
     * rilascio in produzione.
     */
    public function test_the_command_is_actually_registered_and_runnable_as_a_real_cli_process(): void
    {
        // phpunit.xml forces DB_DATABASE=:memory: for the PHPUnit process
        // itself; Symfony Process inherits that same environment by
        // default, so a spawned subprocess would otherwise get its OWN
        // empty, unmigrated in-memory database (a real "no such table"
        // failure, unrelated to command registration). A dedicated,
        // migrated file keeps this test isolated from that inherited
        // setting and from every other test's RefreshDatabase transaction.
        $dbPath = storage_path('framework/testing/newsletter-cleanup-cli-'.bin2hex(random_bytes(6)).'.sqlite');
        touch($dbPath);
        $env = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $dbPath];

        try {
            $migrate = new Process(['php', 'artisan', 'migrate', '--force', '--no-interaction'], base_path(), $env);
            $migrate->setTimeout(60);
            $migrate->mustRun();

            $process = new Process(['php', 'artisan', 'newsletter:reconfirmation-cleanup', '--dry-run'], base_path(), $env);
            $process->setTimeout(30);
            $process->run();

            $output = $process->getOutput().$process->getErrorOutput();

            $this->assertStringNotContainsString(
                'is not defined',
                $output,
                "php artisan newsletter:reconfirmation-cleanup must be registered and runnable as a real CLI process, not only inside PHPUnit's already-booted process. Output:\n".$output,
            );
            $this->assertTrue(
                $process->isSuccessful(),
                "php artisan newsletter:reconfirmation-cleanup --dry-run must exit successfully as a real CLI process. Output:\n".$output,
            );
            $this->assertStringContainsString('Dry-run', $output);
        } finally {
            @unlink($dbPath);
        }
    }

    /**
     * Revisione Codex su PR #536: la configurazione di produzione
     * documentata (.env.production.example) imposta LOG_LEVEL=error, che
     * il canale di log di default applica anche a Log::info() — l'evento
     * di audit sarebbe stato scartato in silenzio proprio nell'ambiente
     * dove serve di più. Il canale dedicato newsletter_reconfirmation_audit
     * ha un livello fisso a 'info', indipendente da LOG_LEVEL: qui si
     * simula esplicitamente quella soglia di produzione sul canale di
     * default e si prova che l'evento arriva comunque sul file dedicato.
     */
    public function test_audit_log_survives_a_production_like_error_only_log_level(): void
    {
        $auditLogPath = storage_path('logs/test-newsletter-reconfirmation-audit-'.uniqid('', true).'.log');
        config([
            'logging.channels.newsletter_reconfirmation_audit.path' => $auditLogPath,
            'logging.channels.stack.level' => 'error',
            'logging.channels.single.level' => 'error',
            'logging.channels.daily.level' => 'error',
        ]);

        $subscriber = Newsletter::subscribe('audit-log-level@example.com');
        NewsletterReconfirmation::create([
            'newsletter_id' => $subscriber->id,
            'token' => Str::random(64),
            'sent_at' => now()->subDays(10),
            'expires_at' => now()->subDays(3),
        ]);

        try {
            $this->artisan('newsletter:reconfirmation-cleanup')->assertExitCode(0);

            $this->assertFileExists($auditLogPath, 'The dedicated audit channel must write its own file regardless of the default channel level.');
            $contents = file_get_contents($auditLogPath);
            $this->assertStringContainsString('Rimozione iscritti newsletter pendenti scaduti.', $contents);
            $this->assertStringContainsString((string) $subscriber->id, $contents);
        } finally {
            @unlink($auditLogPath);
        }
    }

    /**
     * Revisione Codex su PR #536: selezione ed eliminazione erano due
     * passi separati — una cancellazione concorrente tra i due poteva
     * lasciare il log con ID che quella invocazione non aveva realmente
     * rimosso. Non è praticamente simulabile una vera race a due
     * connessioni in questa suite (SQLite in-memory, singolo processo);
     * questo test prova invece l'invariante osservabile che la fix deve
     * comunque garantire ad ogni chiamata: il conteggio e gli ID nel log
     * di audit corrispondono ESATTAMENTE alle righe realmente rimosse dal
     * database, mai a una fotografia presa prima della cancellazione.
     */
    public function test_audit_log_records_exactly_the_ids_actually_deleted(): void
    {
        $auditLogPath = storage_path('logs/test-newsletter-reconfirmation-audit-'.uniqid('', true).'.log');
        config(['logging.channels.newsletter_reconfirmation_audit.path' => $auditLogPath]);

        $subscribers = collect(['race-a@example.com', 'race-b@example.com', 'race-c@example.com'])
            ->map(function (string $email) {
                $subscriber = Newsletter::subscribe($email);
                NewsletterReconfirmation::create([
                    'newsletter_id' => $subscriber->id,
                    'token' => Str::random(64),
                    'sent_at' => now()->subDays(10),
                    'expires_at' => now()->subDays(3),
                ]);

                return $subscriber;
            });

        try {
            $service = app(NewsletterReconfirmationService::class);
            $deletedCount = $service->deleteExpiredPending();

            $this->assertSame(3, $deletedCount);

            $contents = file_get_contents($auditLogPath);
            preg_match('/"subscriber_ids":\[([^\]]*)\]/', $contents, $matches);
            $this->assertNotEmpty($matches, 'audit log entry not found or malformed');
            $loggedIds = array_filter(array_map('trim', explode(',', $matches[1])));

            $this->assertCount($deletedCount, $loggedIds, 'logged subscriber_ids count must match the number of rows actually deleted');
            $this->assertSame($subscribers->pluck('id')->sort()->values()->all(), collect($loggedIds)->map(fn ($id) => (int) $id)->sort()->values()->all());

            foreach ($subscribers as $subscriber) {
                $this->assertDatabaseMissing('newsletter', ['id' => $subscriber->id]);
            }
        } finally {
            @unlink($auditLogPath);
        }
    }
}
