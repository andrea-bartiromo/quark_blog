<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
