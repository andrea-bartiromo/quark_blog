<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prompt 1 (programma 100 prompt): pianificazione categorie (bozza,
 * programmato, pubblicato). Puramente additiva — nessuna categoria
 * esistente cambia stato: `status` di default `published`, così ogni
 * riga già presente resta pubblica esattamente come prima di questa
 * migration (stesso principio già usato da
 * 2026_08_23_170000_add_publish_at_to_content_clusters_table per
 * `ContentCluster::publish_at`). `published_at` viene comunque
 * retrodatato a `created_at` per le righe esistenti, solo per dare un
 * valore editorialmente coerente al campo — mai perché la visibilità ne
 * dipenda (Category::scopePubliclyVisible() considera pubblica ogni riga
 * con status=published indipendentemente da published_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('status', ['draft', 'scheduled', 'published'])
                ->default('published')
                ->after('is_active');
            $table->timestamp('published_at')->nullable()->after('status');
        });

        DB::table('categories')->whereNull('published_at')->update([
            'published_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
