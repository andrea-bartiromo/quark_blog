<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_knowledge_statements', function (Blueprint $table) {
            $table->id();
            $table->string('domanda', 300);
            $table->text('consenso');
            $table->text('incertezza');
            $table->text('cosa_manca')->nullable();
            // Manuale, mai calcolato da updated_at: stesso principio di
            // ArticleRevisionTransparencyService — una data editoriale
            // dichiarata esplicitamente, non una data tecnica di riga.
            $table->date('last_checked_at')->nullable();
            $table->string('last_checked_by')->nullable();
            $table->foreignId('concept_id')->nullable()->constrained('concepts')->nullOnDelete();
            $table->foreignId('content_cluster_id')->nullable()->constrained('content_clusters')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['concept_id']);
            $table->index(['content_cluster_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_knowledge_statements');
    }
};
