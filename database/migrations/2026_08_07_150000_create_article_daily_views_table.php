<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aggregato giornaliero delle views pubbliche per articolo — non un log
     * per singola pageview (quello esiste già in article_views). Una riga
     * per (articolo, giorno editoriale Europe/Rome), incrementata a ogni
     * view valida. Nessun dato storico viene ricostruito qui: la tabella
     * parte vuota e si popola solo dalle view successive all'introduzione
     * di questo sistema (vedi ArticleViewTrackingService).
     */
    public function up(): void
    {
        Schema::create('article_daily_views', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('date');

            $table->unsignedInteger('views')->default(0);

            $table->timestamps();

            $table->unique(['article_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_daily_views');
    }
};
