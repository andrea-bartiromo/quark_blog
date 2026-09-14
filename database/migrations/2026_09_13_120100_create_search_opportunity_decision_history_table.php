<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cantiere 4 (programma "Kairus Organic Discovery"): storico
     * append-only di ogni cambiamento di decisione/stato per opportunità
     * — stesso schema (subject/azione/vecchio-nuovo valore/motivo/chi/
     * quando, $timestamps=false, mai un update/upsert) già in produzione
     * per ProjectActivityLog. Nessuna riga qui viene mai modificata o
     * cancellata dall'applicazione.
     */
    public function up(): void
    {
        Schema::create('search_opportunity_decision_histories', function (Blueprint $table) {
            $table->id();
            $table->string('opportunity_key', 600);
            $table->index('opportunity_key');
            $table->string('action', 40);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_opportunity_decision_histories');
    }
};
