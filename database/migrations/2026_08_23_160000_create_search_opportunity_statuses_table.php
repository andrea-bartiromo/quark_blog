<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un'opportunità (SearchOpportunity) è un valore ricalcolato ad ogni
     * richiesta da search_console_queries — non ha una riga propria e non
     * sopravvive a un nuovo import per costruzione. Questa tabella le dà
     * un'identità stabile SOLO per il workflow editoriale (opportunity_key
     * = type|query|page_url), indipendente da quale import l'ha generata:
     * lo stato assegnato oggi resta valido anche dopo un nuovo import
     * dello stesso periodo o di un periodo successivo con la stessa
     * combinazione query/pagina/tipo.
     */
    public function up(): void
    {
        Schema::create('search_opportunity_statuses', function (Blueprint $table) {
            $table->id();

            $table->string('opportunity_key', 600)->unique();

            // new|reviewed|actioned|dismissed — vedi
            // App\Services\SearchConsole\SearchOpportunityStatusService.
            // Stringa, non enum DB: coerente con lo stile già in uso altrove
            // in questo repository (es. articles.status) ed evita una
            // migration di ALTER per aggiungere un valore futuro.
            $table->string('status', 20)->default('new');

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_opportunity_statuses');
    }
};
