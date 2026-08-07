<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_sends', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained('comm_campaigns')->cascadeOnDelete();
            $table->foreignId('subscriber_id')->constrained('comm_subscribers')->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('provider_message_id');
            $table->index('subscriber_id');

            // Impedisce due Send per lo stesso destinatario sulla stessa
            // campagna — difesa strutturale contro il doppio invio.
            $table->unique(['campaign_id', 'subscriber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_sends');
    }
};
