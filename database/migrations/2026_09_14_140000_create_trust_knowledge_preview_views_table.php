<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_knowledge_preview_views', function (Blueprint $table) {
            $table->id();

            // Append-only: nessun updated_at, un evento non viene mai
            // modificato dopo la creazione. Deliberatamente NESSUN
            // user_id/ip/session/visitor identifier — vedi il docblock del
            // modello TrustKnowledgeStatementPreviewView: privacy-first per
            // scelta di scope (Cantiere 43), stesso principio già in uso in
            // ArticleContinuationEvent.
            // Nomi espliciti e brevi per vincolo FK e indice composito: i
            // nomi auto-generati da Laravel per questa tabella superano il
            // limite di 64 caratteri per identificatore di MySQL/MariaDB —
            // stesso problema già scoperto e documentato in
            // create_article_continuation_events_table.php (lì solo
            // sull'indice; qui anche sul vincolo FK, verificato in CI reale
            // su MariaDB).
            $table->foreignId('trust_knowledge_statement_id')
                ->constrained('trust_knowledge_statements', 'id', 'tkpv_statement_fk')
                ->cascadeOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['trust_knowledge_statement_id', 'created_at'], 'tkpv_statement_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_knowledge_preview_views');
    }
};
