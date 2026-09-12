<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 21 (programma 100-cantieri Kairus): pages:inventory è di sola
 * lettura e sempre di successo — non è un gate, è un catalogo per gli
 * audit a valle (Cantieri 22-29).
 *
 * `categoria` ha sempre un esempio subito dopo `migrate:fresh` (le 7
 * categorie di base seminate da create_categories_table, tutte
 * pubbliche di default — vedi PublicPageInventoryTest); solo `articolo`,
 * `autore` e `percorso` restano senza esempio finché non esiste almeno
 * un Articolo pubblicato o un Percorso pubblicamente visibile.
 */
class PublicPageInventoryReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_every_page_type_and_warns_about_missing_dynamic_samples(): void
    {
        $this->artisan('pages:inventory')
            ->assertExitCode(0)
            ->expectsOutputToContain('articolo, autore, percorso');
    }

    public function test_json_output_is_valid_json(): void
    {
        $this->artisan('pages:inventory', ['--json' => true])->assertExitCode(0);
    }
}
