<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 31 (programma 100-cantieri Kairus), dipende dal Cantiere 30
 * (PublicHealthDashboardService). Stesso pattern già in produzione in
 * questo repository per un workflow editoriale identico —
 * `search_opportunity_statuses` (Mission 6): un'identità stabile per un
 * "finding" che altrimenti non ha mai una riga propria (ricalcolato ad
 * ogni richiesta dai sei audit del Cantiere 30), indipendente da quale
 * esecuzione dell'audit lo ha rilevato. `finding_key` è composto come
 * "{dominio}|{identificatore}" (es. "wcag|contatti",
 * "not_found|/vecchio-path") — stesso stile leggibile già in uso per
 * "opportunity_key" (type|query|page_url), non un hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_finding_statuses', function (Blueprint $table) {
            $table->id();

            $table->string('finding_key', 600)->unique();

            // Uno dei sei domini di App\Services\PublicPages\
            // PublicHealthDashboardService (seo, redirects_canonical,
            // not_found, links, media, wcag) — solo per filtrare/mostrare,
            // mai parte della chiave di unicità (già garantita da
            // finding_key da solo, che incorpora il dominio).
            $table->string('domain', 40);

            // new|in_carico|ignorato — vedi
            // App\Services\PublicPages\AuditFindingStatusService. Stringa,
            // non enum DB, stesso stile già in uso per
            // search_opportunity_statuses.status.
            $table->string('status', 20)->default('new');

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_finding_statuses');
    }
};
