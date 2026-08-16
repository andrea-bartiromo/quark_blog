<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relazione minima subscriber <-> Percorso per "Avvisami quando
     * continua" (missione PR #199, dipende da #197 lifecycle_status e da
     * #198 CommunicationDeliveryService). Riusa comm_subscribers come
     * unica identità/consenso — questa tabella non duplica mai email o
     * stato di consenso globale, registra solo l'INTENTO di seguire uno
     * specifico Percorso.
     *
     * Distinta deliberatamente da comm_sends/communication_deliveries:
     * quelle tracciano invii, questa traccia un'iscrizione (l'intento),
     * esattamente come content_cluster_suggestions non è comm_campaigns.
     *
     * unique(subscriber_id, content_cluster_id): un subscriber non può mai
     * avere due righe attive per lo stesso Percorso — un secondo submit
     * dello stesso form (Parte 16, scenario G) trova sempre la riga
     * esistente, non ne crea una seconda.
     *
     * unsubscribe_token è PROPRIO di questa relazione, mai
     * comm_subscribers.unsubscribe_token: disiscriversi da UN Percorso
     * (Parte 7) non deve mai poter disiscrivere da un altro Percorso o
     * dalla newsletter generale — token diversi, superfici diverse.
     */
    public function up(): void
    {
        Schema::create('content_cluster_subscribers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscriber_id')
                ->constrained('comm_subscribers')
                ->cascadeOnDelete();

            $table->foreignId('content_cluster_id')
                ->constrained('content_clusters')
                ->cascadeOnDelete();

            $table->string('status', 20)->default('active');

            $table->string('unsubscribe_token', 32)->unique();

            $table->timestamp('unsubscribed_at')->nullable();

            $table->timestamps();

            // Nome esplicito e corto: il nome auto-generato da Laravel per
            // questa coppia di colonne supera il limite di 64 caratteri
            // degli identificatori MySQL/MariaDB — un fallimento che SQLite
            // non avrebbe mai rivelato (nessun limite equivalente), trovato
            // solo validando questa migration contro MariaDB reale.
            $table->unique(['subscriber_id', 'content_cluster_id'], 'ccs_subscriber_cluster_unique');
            $table->index('content_cluster_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_cluster_subscribers');
    }
};
