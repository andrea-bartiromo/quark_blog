<?php

namespace Tests\Feature\Deploy;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Kairus Prompt 279 (programma 251-400): ShellCheck su scripts/local-release-check.sh
 * ha segnalato due finding reali (SC2164, SC2034), confermati leggendo il
 * codice prima di correggerli — non richiesta una correzione generica.
 *
 * Questo script compone l'intera suite PHPUnit del progetto al proprio
 * interno: un test end-to-end che lo eseguisse davvero rilancerebbe
 * ricorsivamente l'intera suite da dentro un singolo test, impraticabile.
 * La verifica appropriata per questo script — coerente con il pattern gia'
 * in uso da DeploymentSafetyTest per deploy.sh — resta quindi sul
 * contenuto sorgente statico e sulla sintassi, non sull'esecuzione reale.
 */
class LocalReleaseCheckScriptTest extends TestCase
{
    private function script(): string
    {
        $script = file_get_contents(base_path('scripts/local-release-check.sh'));

        $this->assertIsString($script);

        return $script;
    }

    /**
     * SC2164: senza `set -e` (questo script lo omette deliberatamente, per
     * eseguire ogni controllo anche dopo un fallimento — vedi il commento
     * in testa allo script), un `cd` fallito non fermava affatto lo
     * script: l'esecuzione proseguiva silenziosamente nella directory di
     * invocazione originale invece che nella radice del repository,
     * eseguendo `php artisan test`/Pint/`git diff --check` contro il posto
     * sbagliato senza alcun segnale d'errore chiaro.
     */
    public function test_cd_into_repo_root_fails_closed_instead_of_continuing_silently(): void
    {
        $script = $this->script();

        $this->assertStringContainsString(
            'cd "$REPO_ROOT" || { echo "ERROR: cannot cd into repository root: $REPO_ROOT" >&2; exit 1; }',
            $script
        );
    }

    /**
     * SC2034: DETAIL[phpunit] veniva popolato ma mai letto — l'unica
     * traccia di un fallimento PHPUnit restava l'output scorso via `tail
     * -3` al momento del controllo, perso in una suite lunga. Il
     * riepilogo finale ora lo ristampa quando presente.
     */
    public function test_the_final_summary_prints_the_captured_detail_for_a_failed_check(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('if [[ -n "${DETAIL[$check]:-}" ]]; then', $script);
        $this->assertStringContainsString("printf '    -> %s\\n' \"\${DETAIL[\$check]}\"", $script);

        $summaryPosition = strpos($script, '# ---- Riepilogo ----');
        $detailReadPosition = strpos($script, '${DETAIL[$check]:-}');

        $this->assertNotFalse($summaryPosition);
        $this->assertNotFalse($detailReadPosition);
        $this->assertGreaterThan($summaryPosition, $detailReadPosition, 'DETAIL must be read back inside the final summary loop, not somewhere unrelated.');
    }

    public function test_the_script_has_valid_bash_syntax(): void
    {
        $versionCheck = new Process(['bash', '--version']);
        $versionCheck->run();

        if (! $versionCheck->isSuccessful()) {
            $this->markTestSkipped('No functional Bash shell is available in this environment.');
        }

        $process = new Process(['bash', '-n', base_path('scripts/local-release-check.sh')]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'bash -n (syntax-only check) failed: '.$process->getErrorOutput());
    }
}
