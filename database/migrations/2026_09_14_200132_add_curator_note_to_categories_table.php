<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 49 (programma "100 cantieri Kairus", dipende dai Cantieri
 * 1-8): campo editoriale opzionale, scritto SOLO da un umano in admin,
 * mai auto-generato — stesso pattern di
 * 2026_08_18_...add_curator_note_to_content_clusters_table per
 * ContentCluster::curator_note. Nullable e senza default: ogni categoria
 * esistente resta invariata (categoria.blade.php mostra il testo
 * generico attuale finché nessun editore compila questo campo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('curator_note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('curator_note');
        });
    }
};
