<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suggerimenti di collegamento interno tra articoli — mai un
     * inserimento automatico: ogni riga è una PROPOSTA che la redazione
     * accetta o ignora esplicitamente (vedi App\Services\
     * ArticleLinkSuggestionService). unique(source,target): al più un
     * suggerimento per coppia di articoli, per non riproporre
     * continuamente lo stesso collegamento — se il testo cambia e il
     * collegamento resta pertinente, la riga esistente viene aggiornata,
     * non duplicata.
     */
    public function up(): void
    {
        Schema::create('article_link_suggestions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            $table->foreignId('target_article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            $table->string('anchor_text');

            $table->text('context_excerpt')->nullable();

            $table->string('reason');

            $table->unsignedTinyInteger('confidence_score')->default(0);

            $table->string('status')->default('proposed');

            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['source_article_id', 'target_article_id'], 'als_source_target_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_link_suggestions');
    }
};
