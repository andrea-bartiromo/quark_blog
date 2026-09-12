<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit append-only del consenso newsletter. Non ha una foreign key
     * distruttiva: l’evento di eliminazione deve sopravvivere alla riga
     * newsletter che documenta.
     */
    public function up(): void
    {
        Schema::create('newsletter_consent_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newsletter_id')->nullable()->index();
            $table->string('email_hash', 64)->index();
            $table->string('event_type', 64);
            $table->unsignedTinyInteger('attempt_number')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['newsletter_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_consent_events');
    }
};
