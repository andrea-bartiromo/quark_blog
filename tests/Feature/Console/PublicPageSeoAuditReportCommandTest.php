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

    /**
     * Storico: prima del commit e8049a4 ("fix: usa la root media servita
     * e aggiunge i canonical mancanti"), ricerca.blade.php mancava del
     * canonical ed era l'unico finding su un ambiente appena migrato —
     * questo test verificava quindi il ramo "almeno un problema
     * segnalato". Il fix ha eliminato quel finding: su un ambiente pulito
     * l'audit non ne rileva più nessuno, quindi il comando imbocca
     * legittimamente il ramo "Nessun problema rilevato" (vedi
     * PublicPageSeoAuditReport::handle()). Il ramo "pagine non
     * verificate" resta invece valido: in un ambiente appena migrato
     * senza Articoli/Percorsi, articolo/autore/percorso restano sempre
     * senza un esempio pubblico.
     */
    public function test_lists_every_page_type_and_warns_about_unchecked_types(): void
    {
        $this->artisan('pages:seo-audit')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun problema rilevato tra le pagine verificate.')
            ->expectsOutputToContain('non verificati per assenza di un esempio pubblico');
    }

    public function test_json_output_is_produced_successfully(): void
    {
        $this->artisan('pages:seo-audit', ['--json' => true])->assertExitCode(0);
    }
}
