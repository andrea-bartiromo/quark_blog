<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 1 (programma "Kairus Organic Discovery"). Una riga per
 * property/periodo/tipo di report, sempre la piu' recente (upsert, mai una
 * riga per ogni singolo import storico: search_console_queries resta gia'
 * l'unica fonte di verita' idempotente-per-periodo, questa tabella ne
 * registra solo la "copertura effettiva" corrente per la redazione).
 *
 * 'property' non e' mai null: un import senza property esplicita usa
 * config('search-console.default_property') o config('app.url') come
 * valore risolto, cosi' l'indice unico sotto puo' davvero deduplicare (in
 * MySQL/MariaDB NULL non e' mai uguale a NULL in un indice unico).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_import_coverage', function (Blueprint $table) {
            $table->id();

            $table->string('property', 255);
            $table->date('period_start');
            $table->date('period_end');

            // 'query_only' (export Query standard, nessuna dimensione
            // pagina) oppure 'query_page' (formato combinato query+page).
            $table->string('report_type', 20);

            $table->unsignedInteger('row_count');
            $table->unsignedInteger('matched_count');
            $table->unsignedInteger('unmatched_count');
            $table->unsignedInteger('pages_observed_count');

            // Sempre 'manual_csv' in questo cantiere: nessuna integrazione
            // API esiste ancora (vedi Cantiere 6, solo architettura futura).
            $table->string('origin', 20)->default('manual_csv');

            $table->string('import_batch', 64);
            $table->timestamp('imported_at');

            $table->timestamps();

            $table->unique(
                ['property', 'period_start', 'period_end', 'report_type'],
                'scic_property_period_report_type_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_import_coverage');
    }
};
