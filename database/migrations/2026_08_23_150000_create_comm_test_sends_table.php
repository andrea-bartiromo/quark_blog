<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_test_sends', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Traccia separata e deliberatamente NON collegata a comm_sends:
            // un invio di test non è mai, per costruzione, una riga della
            // coda bulk (CampaignTestSendService non tocca comm_sends né
            // CampaignStateMachine/SendStateMachine) — vedi il docblock del
            // servizio per il ragionamento completo.
            $table->foreignId('campaign_id')->constrained('comm_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('comm_subscribers')->cascadeOnDelete();
            $table->foreignId('sender_profile_id')->nullable()->constrained('comm_sender_profiles')->nullOnDelete();

            $table->string('status');
            $table->string('provider_message_id')->nullable();
            $table->string('failure_reason')->nullable();

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_test_sends');
    }
};
