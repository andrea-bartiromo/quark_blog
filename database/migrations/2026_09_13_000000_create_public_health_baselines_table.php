<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 35 (programma 100-cantieri Kairus), dipende dal Cantiere 30
 * (PublicHealthDashboardService). Una riga per dominio/mese, MAI una riga
 * aggregata su più domini: 'checked_count'/'total_count' sono i
 * denominatori del dominio che ha generato quella riga (pagine per
 * seo/wcag, link per links, media per media, ...) — universi diversi per
 * ogni dominio (vedi i docblock di PublicHealthDashboardService), quindi
 * "denominatori separati" non è solo un requisito della UI ma un vincolo
 * dello schema stesso: nessuna colonna qui somma o media denominatori tra
 * domini diversi. Nullable perché non tutti i domini ne espongono uno
 * (es. not_found: ogni riga è un finding per costruzione, nessun
 * "verificato su" ha senso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_health_baselines', function (Blueprint $table) {
            $table->id();

            // Uno dei sei domini reali di PublicHealthDashboardService
            // (seo, redirects_canonical, not_found, links, media, wcag).
            $table->string('domain', 40);

            // Formato 'YYYY-MM' (Carbon::format('Y-m')), non una data:
            // un solo record per dominio/mese, mai per giorno.
            $table->string('period', 7);

            $table->unsignedInteger('finding_count');
            $table->unsignedInteger('open_count');
            $table->unsignedInteger('dismissed_count');
            $table->unsignedInteger('high_open_count');

            // Denominatori: SOLO quelli del proprio dominio, mai combinati
            // con quelli di un altro dominio. Nullable perché non ogni
            // dominio ne espone uno (es. not_found, redirects_canonical).
            $table->unsignedInteger('checked_count')->nullable();
            $table->unsignedInteger('total_count')->nullable();

            $table->timestamp('recorded_at');

            $table->timestamps();

            $table->unique(['domain', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_health_baselines');
    }
};
