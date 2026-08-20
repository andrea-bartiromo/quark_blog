<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indice giustificato da misura, non da intuizione: con 10.000 righe in
     * articles (vedi audit /admin/articoli, Kairus — Admin Articoli
     * scalabile), EXPLAIN mostrava "Using filesort" su ORDER BY created_at
     * DESC LIMIT — senza filtri (la vista di default) e con ciascun filtro
     * singolo (stato, categoria, autore) — perché nessun indice copriva
     * l'ordinamento. Con questo indice, lo stesso EXPLAIN mostra una
     * scansione diretta dell'indice in ordine decrescente che si ferma
     * dopo le prime 25 righe (nessun filesort, nessuna scansione completa
     * della tabella) per la vista di default e per ciascun filtro singolo.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
