<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mission 31 — Search Zero-Result Diagnostics. Un'unica riga per query
 * normalizzata (non un evento per ricerca): "Prefer normalized query/
 * count/time aggregates" dalla formulazione della missione. Nessuna
 * colonna utente/sessione/IP/user-agent — deliberatamente, per "Do not
 * store unnecessary personal information": il testo digitato è l'unico
 * dato, aggregato per conteggio, mai per chi lo ha digitato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_zero_result_queries', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_query');
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamps();

            $table->unique('normalized_query');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_zero_result_queries');
    }
};
