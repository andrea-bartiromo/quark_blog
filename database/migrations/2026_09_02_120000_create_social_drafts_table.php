<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace Social Admin V1 — ledger editoriale interno, distinto e mai
 * collegato a `social_publications` (ledger di delivery/provider, mai
 * modificato da questa missione). Nessuna colonna di consegna qui
 * (token, remote_id, remote_url, attempt_count, last_error_*): una
 * futura fase provider potrà collegare esplicitamente le due tabelle,
 * non questa migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_drafts', function (Blueprint $table) {
            $table->id();

            // restrict, non cascade: un articolo non deve poter sparire
            // sotto una bozza Social ancora in lavorazione — se serve
            // cancellarlo, la bozza va prima gestita esplicitamente.
            $table->foreignId('article_id')->constrained()->restrictOnDelete();

            $table->string('channel', 32);

            // draft è il default sicuro: ogni riga nasce non pronta per
            // nessuna azione pubblica.
            $table->string('status', 32)->default('draft');

            $table->text('copy')->nullable();
            $table->text('destination_url')->nullable();
            $table->boolean('use_utm')->default(true);
            $table->string('utm_campaign', 191)->nullable();

            // Sempre UTC in storage, stessa convenzione di Article::published_at
            // (vedi Article::EDITORIAL_TIMEZONE) — la conversione Europe/Rome
            // avviene solo in presentazione/input, mai nello schema.
            $table->timestamp('scheduled_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index('article_id');
            $table->index(['status', 'scheduled_at']);
            $table->index(['channel', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_drafts');
    }
};
