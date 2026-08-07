<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comm_templates', function (Blueprint $table) {
            $table->foreign('active_version_id')->references('id')->on('comm_template_versions')->nullOnDelete();
        });

        Schema::table('comm_campaigns', function (Blueprint $table) {
            // template_id esiste già dal Blocco B1 (senza FK, perché
            // comm_templates non esisteva ancora). Il vincolo si aggiunge
            // ora che la tabella è disponibile.
            $table->foreign('template_id')->references('id')->on('comm_templates')->nullOnDelete();

            // Puntamento alla versione esatta usata: se il template cambia
            // in futuro, la campagna resta ancorata a questa versione e non
            // segue automaticamente le modifiche (Blueprint Sezione 7).
            $table->unsignedBigInteger('template_version_id')->nullable()->after('template_id');
            $table->foreign('template_version_id')->references('id')->on('comm_template_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comm_campaigns', function (Blueprint $table) {
            $table->dropForeign(['template_version_id']);
            $table->dropColumn('template_version_id');
            $table->dropForeign(['template_id']);
        });

        Schema::table('comm_templates', function (Blueprint $table) {
            $table->dropForeign(['active_version_id']);
        });
    }
};
