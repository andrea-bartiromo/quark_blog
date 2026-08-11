<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Codex (PR #165, round 12): un target_article_id con cascadeOnDelete()
     * cancella l'intera riga del suggerimento quando l'articolo target viene
     * eliminato — se ciò accade tra il click su "Inserisci" (link già
     * fisicamente presente nel body inviato dal form) e il salvataggio della
     * source, App\Services\ArticleLinkSuggestionService::markAccepted() non
     * trova più alcuna riga da cui recuperare lo slug da ripulire, e il body
     * viene salvato con un <a> ormai verso il nulla. nullOnDelete() (invece
     * di cancellare) preserva la riga — con target_article_id azzerato — così
     * la stessa revalidazione già esistente ad ogni salvataggio della source
     * può ancora agire, usando lo snapshot target_slug (aggiunto qui,
     * popolato alla creazione/aggiornamento del suggerimento) per sapere
     * quale slug ripulire anche quando l'Article target non esiste più.
     */
    public function up(): void
    {
        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->string('target_slug')->nullable()->after('target_article_id');
        });

        // Codex (PR #165, round 14): il codice applicativo popola
        // target_slug solo alla creazione/aggiornamento di una riga
        // (analyzeForSource()/analyzeForNewTarget()) — le righe GIÀ
        // esistenti su un'installazione in produzione al momento di questo
        // deploy resterebbero con target_slug NULL per sempre (nessun
        // evento le tocca finché non ridiventano 'proposed'). Se il loro
        // target viene eliminato dopo questo deploy, sia target_article_id
        // sia target_slug sarebbero null: supersedeAndStripIfUnsafe() non
        // avrebbe alcuno slug da cui ripulire il link, lasciandolo rotto
        // nel body per sempre. Sottoquery correlata (non un JOIN in
        // UPDATE): stessa sintassi SQL standard, portabile identica su
        // SQLite e MySQL.
        DB::statement('
            UPDATE article_link_suggestions
            SET target_slug = (
                SELECT slug FROM articles WHERE articles.id = article_link_suggestions.target_article_id
            )
            WHERE target_article_id IS NOT NULL
        ');

        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->dropForeign(['target_article_id']);
        });

        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->unsignedBigInteger('target_article_id')->nullable()->change();
        });

        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->foreign('target_article_id')
                ->references('id')->on('articles')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->dropForeign(['target_article_id']);
        });

        // Codex (PR #165, round 13): nullOnDelete() (questa migrazione,
        // direzione up()) rende NULL target_article_id un vero stato
        // raggiungibile in produzione (target eliminato dopo la
        // creazione del suggerimento) — un rollback che trovi righe in
        // quello stato farebbe fallire la conversione a nullable(false)
        // sotto. Non c'è modo di recuperare a quale target puntassero
        // (l'unico riferimento era la relazione ormai assente): la
        // stessa riga sarebbe comunque scomparsa sotto il vecchio
        // cascadeOnDelete(), quindi eliminarla qui riporta i dati allo
        // stato equivalente che il vincolo precedente avrebbe prodotto.
        DB::table('article_link_suggestions')->whereNull('target_article_id')->delete();

        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->unsignedBigInteger('target_article_id')->nullable(false)->change();
        });

        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->foreign('target_article_id')
                ->references('id')->on('articles')
                ->cascadeOnDelete();
        });

        Schema::table('article_link_suggestions', function (Blueprint $table) {
            $table->dropColumn('target_slug');
        });
    }
};
