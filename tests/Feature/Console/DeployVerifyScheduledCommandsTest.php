<?php

namespace Tests\Feature\Console;

use App\Console\Commands\FetchNewsAndGenerateDrafts;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * `deploy:verify-scheduled-commands` is the exit-code contract deploy.sh
 * relies on (mirrors deploy:asset-drift's role for the public asset
 * drift gate): 0 when every command-based Schedule event registered in
 * THIS process is registered by Artisan, non-zero otherwise.
 *
 * Revisione Codex su PR #541: la prima versione leggeva
 * routes/console.php come testo via regex — una riga commentata
 * avrebbe comunque richiesto il comando, mentre una chiamata non
 * letterale (per variabile o per classe) sarebbe sfuggita al controllo.
 * Corretto interrogando `Schedule::events()`, cioè lo scheduler
 * REALMENTE COSTRUITO da questo processo — non il testo sorgente. I
 * test qui sotto esercitano direttamente quel meccanismo aggiungendo
 * eventi al singleton Schedule già risolto dal container (esattamente
 * come farebbe una riga eseguita di routes/console.php), non editando
 * il file su disco: editarlo a runtime non avrebbe alcun effetto,
 * perché Schedule::class è un singleton costruito una sola volta
 * durante il bootstrap di questo stesso processo di test, prima che il
 * corpo del test giri (routes/console.php non viene mai ri-richiesto a
 * metà test). La prova a sottoprocesso reale — che riflette un editing
 * del file genuino, incluso il caso della riga commentata, che qui non
 * si può testare in-process proprio per questo motivo — resta in
 * DeploymentSafetyTest::test_production_deploy_verify_scheduled_commands_gate_fails_closed_on_a_real_broken_release_worktree.
 */
class DeployVerifyScheduledCommandsTest extends TestCase
{
    public function test_it_succeeds_when_every_scheduled_command_is_registered(): void
    {
        $exitCode = Artisan::call('deploy:verify-scheduled-commands');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('scheduled commands are registered', Artisan::output());
    }

    public function test_it_fails_when_a_scheduled_command_is_not_registered(): void
    {
        app(Schedule::class)->command('this-command-does-not-exist')->daily();

        $exitCode = Artisan::call('deploy:verify-scheduled-commands');
        // Artisan::output() wraps a Symfony BufferedOutput::fetch(),
        // which empties the buffer on read — capture it once.
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('this-command-does-not-exist', $output);
        $this->assertStringContainsString('newsletter:reconfirmation-cleanup incident class', $output);
    }

    /**
     * La preoccupazione centrale della revisione Codex: un comando
     * schedulato passato come VARIABILE, non come stringa letterale nel
     * punto di chiamata, non deve poter sfuggire al controllo. Il
     * meccanismo precedente (regex su routes/console.php) lo avrebbe
     * mancato, perché nel sorgente non compare alcuna stringa letterale
     * da far corrispondere.
     */
    public function test_it_detects_a_missing_command_scheduled_via_a_variable_not_a_literal_string(): void
    {
        $commandName = 'this-command-does-not-exist-via-variable';
        app(Schedule::class)->command($commandName)->daily();

        $exitCode = Artisan::call('deploy:verify-scheduled-commands');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('this-command-does-not-exist-via-variable', $output);
    }

    /**
     * L'altra metà della stessa preoccupazione: un comando schedulato
     * per CLASSE (`Schedule::command(SomeCommand::class)`) deve essere
     * riconosciuto correttamente come soddisfatto quando il comando è
     * realmente registrato — non deve produrre un falso allarme solo
     * perché non è un nome letterale.
     */
    public function test_it_correctly_resolves_a_command_scheduled_via_its_class_name(): void
    {
        app(Schedule::class)->command(FetchNewsAndGenerateDrafts::class)->daily();

        $exitCode = Artisan::call('deploy:verify-scheduled-commands');

        $this->assertSame(0, $exitCode);
    }

    public function test_it_fails_closed_when_the_scheduler_has_no_command_based_events(): void
    {
        app()->instance(Schedule::class, new Schedule);

        $exitCode = Artisan::call('deploy:verify-scheduled-commands');

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('no command-based events', Artisan::output());
    }
}
