<?php

namespace Tests\Feature\Deploy;

use App\Services\Deploy\FrontControllerHtaccessAudit;
use Tests\TestCase;

/**
 * Cantiere 16 (programma 100-cantieri Kairus, dipende dal Cantiere 15 —
 * vedi docs/CPANEL_FRONT_CONTROLLER_RUNBOOK.md).
 * App\Services\Deploy\FrontControllerHtaccessAudit verifica che
 * public/.htaccess contenga ancora le direttive critiche che il runbook
 * documenta blocco per blocco — l'unica parte del livello Apache/cPanel
 * verificabile in CI, perché è l'unico file git-tracked di quel livello.
 */
class FrontControllerHtaccessAuditTest extends TestCase
{
    public function test_the_real_public_htaccess_in_this_repository_passes(): void
    {
        $report = app(FrontControllerHtaccessAudit::class)->report();

        $this->assertTrue($report['ok'], 'Direttive mancanti: '.implode(', ', $report['missing']));
        $this->assertTrue($report['exists']);
        $this->assertSame([], $report['missing']);
    }

    public function test_fails_closed_when_the_file_does_not_exist(): void
    {
        $missingPath = sys_get_temp_dir().'/kairus-htaccess-audit-test-missing-'.uniqid().'/.htaccess';

        $report = app(FrontControllerHtaccessAudit::class)->report($missingPath);

        $this->assertFalse($report['ok']);
        $this->assertFalse($report['exists']);
        $this->assertNotEmpty($report['missing']);
    }

    public function test_flags_a_missing_env_block_rule(): void
    {
        $path = $this->htaccessFixtureWithout('RewriteRule ^\.env$ - [F,L]');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(['Blocco accesso a .env'], $report['missing']);
    }

    public function test_flags_a_missing_front_controller_rewrite(): void
    {
        $path = $this->htaccessFixtureWithout('RewriteRule ^ index.php [L]');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(['Front controller (rewrite verso index.php)'], $report['missing']);
    }

    public function test_flags_every_missing_directive_independently(): void
    {
        $path = $this->htaccessFixtureWithout('X-Content-Type-Options', 'X-Frame-Options');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(
            ['Header X-Content-Type-Options', 'Header X-Frame-Options'],
            $report['missing']
        );
    }

    /**
     * Finding Codex (P1, PR #563): una direttiva disattivata commentandola
     * (Apache la ignora) lasciava comunque il testo del marker nel file
     * grezzo, quindi il controllo per sottostringa la vedeva ancora
     * "presente". Verifica che la stessa identica riga, se preceduta da
     * "#", venga trattata come assente.
     */
    public function test_flags_a_directive_that_has_been_commented_out(): void
    {
        $contents = (string) file_get_contents(public_path('.htaccess'));
        $contents = str_replace(
            'RewriteRule ^\.env$ - [F,L]',
            '# RewriteRule ^\.env$ - [F,L]',
            $contents
        );

        $path = sys_get_temp_dir().'/kairus-htaccess-audit-test-'.uniqid().'.htaccess';
        file_put_contents($path, $contents);
        register_shutdown_function(fn () => @unlink($path));

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(['Blocco accesso a .env'], $report['missing']);
    }

    /**
     * Finding Codex (P1, PR #563): il tag generico <FilesMatch era
     * soddisfatto anche dai due blocchi di cache statica più avanti nel
     * file, quindi rimuovere SOLO il blocco delle estensioni sensibili
     * (.sql/.bak/ecc.) non veniva rilevato. Il marker ora è l'espressione
     * delle estensioni stessa, specifica di quell'unico blocco.
     */
    public function test_flags_a_missing_sensitive_extensions_block_even_though_other_filesmatch_blocks_remain(): void
    {
        $path = $this->htaccessFixtureWithout('\.(env|log|sqlite|sh|bak|config|dist|fla|inc|ini|log|psd|sh|sql|swp|tar|gz)$');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(['Blocco file sensibili aggiuntivi (estensioni .sql/.bak/ecc.)'], $report['missing']);

        // Prova indipendente che i blocchi <FilesMatch> di cache statica,
        // rimasti intatti, non bastino da soli a far apparire il file
        // "a posto" — esattamente il falso negativo segnalato da Codex.
        $this->assertStringContainsString('<FilesMatch', (string) file_get_contents($path));
    }

    /**
     * Finding Codex (P2, PR #563): senza !-f, il rewrite verso index.php
     * farebbe passare da Laravel anche i file statici già esistenti
     * (CSS, JS, immagini) — un controllo che guardasse solo la riga
     * finale del rewrite non se ne sarebbe accorto.
     */
    public function test_flags_a_missing_static_file_exclusion_condition_on_the_front_controller_rewrite(): void
    {
        $path = $this->htaccessFixtureWithout('RewriteCond %{REQUEST_FILENAME} !-f');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(
            ['Front controller: i file statici esistenti non passano da index.php (!-f)'],
            $report['missing']
        );
    }

    /**
     * Finding Codex (P2, PR #563): il runbook elenca Referrer-Policy
     * insieme agli altri due header di sicurezza, ma mancava dai marker
     * richiesti.
     */
    public function test_flags_a_missing_referrer_policy_header(): void
    {
        $path = $this->htaccessFixtureWithout('Referrer-Policy');

        $report = app(FrontControllerHtaccessAudit::class)->report($path);

        $this->assertFalse($report['ok']);
        $this->assertSame(['Header Referrer-Policy'], $report['missing']);
    }

    private function htaccessFixtureWithout(string ...$linesToRemove): string
    {
        $contents = (string) file_get_contents(public_path('.htaccess'));

        foreach ($linesToRemove as $line) {
            $contents = str_replace($line, '', $contents);
        }

        $path = sys_get_temp_dir().'/kairus-htaccess-audit-test-'.uniqid().'.htaccess';
        file_put_contents($path, $contents);
        register_shutdown_function(fn () => @unlink($path));

        return $path;
    }
}
