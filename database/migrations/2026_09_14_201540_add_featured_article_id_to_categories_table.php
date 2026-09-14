<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 50 (programma "100 cantieri Kairus", dipende dal Cantiere
 * 49): selezione manuale di un articolo "in evidenza" per categoria —
 * asse ORTOGONALE ad `Article::featured` (hero homepage sito-wide, un
 * solo articolo per l'intero sito, Cantiere 36) e stesso pattern di
 * `ContentCluster::pillar_article_id`. Nullable, `nullOnDelete()`: se
 * l'articolo scelto viene eliminato la categoria torna semplicemente
 * senza featured, mai un vincolo che blocchi la cancellazione
 * dell'articolo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('featured_article_id')
                ->nullable()
                ->after('curator_note')
                ->constrained('articles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['featured_article_id']);
            $table->dropColumn('featured_article_id');
        });
    }
};
