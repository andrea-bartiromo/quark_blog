<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * EDITORIAL SAFETY — snapshot dei campi editoriali di un articolo,
     * scritto SOLO immediatamente prima di applicare un salvataggio
     * esplicito riuscito (mai dall'autosave locale): protegge dal caso di
     * un salvataggio riuscito ma sbagliato (es. contenuto cancellato per
     * errore e Salva cliccato), distinto dalla perdita di lavoro
     * pre-salvataggio già coperta dall'autosave locale. Vedi
     * docs/article-revision-history.md.
     */
    public function up(): void
    {
        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            // Nullable: l'autore dell'articolo può essere stato cancellato
            // (l'articolo e le sue revisioni restano, l'attribuzione si perde
            // — stesso comportamento già scelto per ActivityLog.user_id).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('excerpt')->nullable();
            $table->longText('body');
            $table->string('category');
            $table->string('status');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');
    }
};
