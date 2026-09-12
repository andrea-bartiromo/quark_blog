<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 24 (programma 100-cantieri Kairus). Nessun registro dei 404
 * reali esisteva prima di questa migrazione (verificato — nessuna
 * tabella, nessun modello, nessun hook). Il Cantiere 23
 * (RedirectAndCanonicalIntegrityAudit) verifica attivamente un insieme
 * noto di URL attesi; questo registro cattura invece i 404 REALMENTE
 * incontrati dal traffico pubblico, aggregati per path, cosi' un
 * editore puo' scoprire link rotti che nessun audit conosceva in
 * anticipo (referrer esterni, vecchi bookmark, refusi di battitura).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('not_found_hits', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->text('last_referer')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();

            $table->index('hits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_hits');
    }
};
