<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indice composito benchmarkato su MariaDB reale (10.000 articoli, ~90%
 * published/5% draft/5% scheduled — vedi report S4). La quasi totalità
 * delle query pubbliche filtra su `status = 'published' AND published_at
 * <= now()` ordinando per `published_at desc` (Article::scopePublished()):
 * con solo l'indice singolo su `status` esistente, MariaDB seleziona ~90%
 * delle righe (tutte le pubblicate) e applica un filesort per ordinare —
 * 19,2ms medi su 20 esecuzioni per una query che restituisce solo 6 righe
 * (EXPLAIN: "Using index condition; Using where; Using filesort", ~9.000
 * righe esaminate). Con l'indice composito: 0,83ms medi (23x più veloce),
 * stesso EXPLAIN senza più filesort ("Using where", nessun "Using
 * filesort"). Non toccato l'indice su article_views(viewed_at):
 * benchmarkato separatamente, già a ~2ms anche con scan completo — nessuna
 * evidenza di beneficio reale, non aggiunto (vedi report).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->index(['status', 'published_at'], 'articles_status_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_status_published_at_index');
        });
    }
};
