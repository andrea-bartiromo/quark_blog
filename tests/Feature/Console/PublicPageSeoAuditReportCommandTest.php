<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 22 (programma 100-cantieri Kairus): pages:seo-audit è di sola
 * lettura e sempre di successo — non è un gate di rilascio, è un
 * catalogo di findings per un editore/operatore.
 */
class PublicPageSeoAuditReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_every_page_type_and_warns_about_findings_and_unchecked_types(): void
    {
        $this->artisan('pages:seo-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('segnalano almeno un problema')
            ->expectsOutputToContain('non verificati per assenza di un esempio pubblico');
    }

    public function test_json_output_is_produced_successfully(): void
    {
        $this->artisan('pages:seo-audit', ['--json' => true])->assertExitCode(0);
    }
}
