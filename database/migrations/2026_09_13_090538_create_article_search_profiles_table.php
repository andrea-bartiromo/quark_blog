<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantiere 2 (programma "Kairus Organic Discovery"). Una riga per
 * articolo (1:1, article_id unico): il "profilo di ricerca editoriale"
 * (intento, query, domande dei lettori, tipo contenuto, livello,
 * freschezza) resta sempre facoltativo e non pubblico — nessuna colonna
 * qui è letta da alcuna pagina pubblica o dalla pubblicazione stessa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_search_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('article_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('primary_intent', 500)->nullable();
            $table->string('primary_query', 255)->nullable();

            // Liste brevi (validate lato form request, max 10 voci
            // ciascuna): non un elenco arbitrariamente lungo di keyword,
            // rappresentano un intento editoriale reale.
            $table->json('secondary_queries')->nullable();
            $table->json('reader_questions')->nullable();

            $table->string('content_type', 20)->nullable();
            $table->string('reader_level', 20)->nullable();

            $table->date('last_editorial_review_at')->nullable();
            $table->text('freshness_note')->nullable();
            $table->text('evidence_scope')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_search_profiles');
    }
};
