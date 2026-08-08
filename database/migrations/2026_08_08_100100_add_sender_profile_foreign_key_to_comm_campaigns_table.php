<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comm_campaigns', function (Blueprint $table) {
            // sender_profile_id esiste già dal Blocco B1 (senza FK, perché
            // comm_sender_profiles non esisteva ancora). Il vincolo si
            // aggiunge ora che la tabella è disponibile — stesso schema già
            // usato per template_id in Comunicazione B3.
            $table->foreign('sender_profile_id')->references('id')->on('comm_sender_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('comm_campaigns', function (Blueprint $table) {
            $table->dropForeign(['sender_profile_id']);
        });
    }
};
