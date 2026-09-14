<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cantiere 4 (programma "Kairus Organic Discovery"): una decisione
     * editoriale tracciabile per opportunità di ricerca (Missione 6/46,
     * SearchOpportunity/SearchOpportunityStatus) — mai un'azione
     * automatica, mai una pubblicazione automatica. Una riga per
     * opportunity_key (stessa identità stabile type|query|page_url già
     * usata da SearchOpportunityStatus), aggiornata nel tempo; lo storico
     * di ogni cambiamento vive in search_opportunity_decision_history
     * (append-only, mai qui).
     */
    public function up(): void
    {
        Schema::create('search_opportunity_decisions', function (Blueprint $table) {
            $table->id();
            // TEXT, non indicizzata: tipo (~28 caratteri) + query (255) +
            // page_url (500) può superare 600 caratteri, e un indice
            // univoco su una colonna larga in utf8mb4 supererebbe il
            // limite di prefisso InnoDB (3072 byte, quindi 768 caratteri
            // in utf8mb4) — vedi Codex, PR #590. L'unicità reale vive
            // sull'hash a lunghezza fissa qui sotto.
            $table->text('opportunity_key');
            $table->string('opportunity_key_hash', 64)->unique();
            $table->string('opportunity_type', 60);
            $table->string('opportunity_query', 255);
            $table->string('decision_type', 20);
            $table->text('rationale')->nullable();

            // Nessun vincolo FK reale su article_id/project_task_id: un
            // articolo o un'attività di progetto eliminati non devono mai
            // cancellare o bloccare la decisione storica — stesso schema
            // già scelto per project_tasks.article_id (vedi la sua
            // migration per la stessa motivazione).
            $table->unsignedBigInteger('article_id')->nullable();
            $table->index('article_id');
            $table->unsignedBigInteger('project_task_id')->nullable();
            $table->index('project_task_id');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Baseline: catturata UNA SOLA VOLTA, alla prima decisione
            // registrata per questa opportunity_key — mai ricalcolata da
            // decisioni successive (vedi SearchOpportunityDecisionService::record()).
            $table->unsignedInteger('baseline_clicks')->nullable();
            $table->unsignedInteger('baseline_impressions')->nullable();
            $table->float('baseline_ctr')->nullable();
            $table->float('baseline_position')->nullable();
            $table->timestamp('baseline_captured_at')->nullable();

            // Misurazioni successive (Missione: confronto esito a 28/90
            // giorni) — popolate in sola lettura da un comando artisan
            // dedicato, mai da una richiesta web.
            $table->unsignedInteger('measured_28d_clicks')->nullable();
            $table->unsignedInteger('measured_28d_impressions')->nullable();
            $table->float('measured_28d_ctr')->nullable();
            $table->float('measured_28d_position')->nullable();
            $table->timestamp('measured_28d_at')->nullable();

            $table->unsignedInteger('measured_90d_clicks')->nullable();
            $table->unsignedInteger('measured_90d_impressions')->nullable();
            $table->float('measured_90d_ctr')->nullable();
            $table->float('measured_90d_position')->nullable();
            $table->timestamp('measured_90d_at')->nullable();

            $table->timestamps();

            $table->index('decision_type');
            $table->index('baseline_captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_opportunity_decisions');
    }
};
