<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Al più un progetto alla volta può essere il "progetto editoriale
            // predefinito" a cui collegare automaticamente i nuovi articoli
            // (Blocco F). L'unicità è garantita a livello applicativo nel
            // modello Project, non da un vincolo DB — stesso approccio già
            // usato per active_version_id in comm_templates.
            $table->boolean('is_default_editorial')->default(false)->after('operational_status');

            $table->index('is_default_editorial');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['is_default_editorial']);
            $table->dropColumn('is_default_editorial');
        });
    }
};
