<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Stato verifica della fonte primaria
            $table->enum('verification_status', [
                'unverified',    // non ancora verificato
                'in_progress',   // in corso di verifica
                'verified',      // verificato sulla fonte primaria
                'needs_update',  // verificato ma necessita aggiornamento
            ])->default('unverified')->after('published_at');

            // Note di verifica (chi ha verificato, quando, su quale fonte)
            $table->text('verification_notes')->nullable()->after('verification_status');

            // Data e ora dell'ultima verifica
            $table->timestamp('verified_at')->nullable()->after('verification_notes');

            // Chi ha verificato (nome del redattore)
            $table->string('verified_by')->nullable()->after('verified_at');

            // Fonti primarie citate (strutturate)
            $table->text('primary_sources')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'verification_notes',
                'verified_at',
                'verified_by',
                'primary_sources',
            ]);
        });
    }
};
