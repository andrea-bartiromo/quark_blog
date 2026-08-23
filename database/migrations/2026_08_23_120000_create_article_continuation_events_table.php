<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('article_continuation_events', function (Blueprint $table) {
            $table->id();

            // 'impression' | 'second_read_start' — vedi
            // App\Models\ArticleContinuationEvent. Append-only: nessun
            // updated_at, un evento non viene mai modificato dopo la
            // creazione.
            $table->string('event_type', 24);

            $table->foreignId('source_article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            $table->foreignId('target_article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();

            // Nomi espliciti e brevi: il nome auto-generato da Laravel per
            // questi indici compositi supera il limite di 64 caratteri per
            // identificatore di MySQL/MariaDB (scoperto eseguendo questa
            // migration su MariaDB reale, non su SQLite, che non applica
            // quel limite).
            $table->index(['source_article_id', 'target_article_id', 'event_type'], 'ace_source_target_type_idx');
            $table->index(['target_article_id', 'event_type', 'created_at'], 'ace_target_type_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_continuation_events');
    }
};
