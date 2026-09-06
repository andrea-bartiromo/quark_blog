<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un tentativo di riconferma = una riga. Mai una colonna sovrascritta
     * sulla tabella newsletter: così ogni invio resta un evento tracciabile
     * (audit invio/conferma) e un nuovo invio non cancella la prova che un
     * invio precedente sia mai avvenuto o sia scaduto senza risposta.
     */
    public function up(): void
    {
        Schema::create('newsletter_reconfirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained('newsletter')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['newsletter_id', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_reconfirmations');
    }
};
