<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Percorsi Scheduling V1 (docs/PERCORSI_SCHEDULING_V1_SPEC.md, PR #295).
     * Additiva soltanto: nessun backfill, nessuna attivazione/disattivazione
     * di Percorsi esistenti. Ogni Percorso legacy (is_active=true,
     * publish_at=null) resta pubblico esattamente come oggi — vedi
     * ContentCluster::isPubliclyVisible().
     */
    public function up(): void
    {
        Schema::table('content_clusters', function (Blueprint $table) {
            $table->timestamp('publish_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('content_clusters', function (Blueprint $table) {
            $table->dropColumn('publish_at');
        });
    }
};
