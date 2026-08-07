<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comm_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('comm_templates')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->json('content')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Solo created_at: una versione è immutabile per definizione,
            // non esiste un "updated_at" da tracciare — stesso principio di
            // CommunicationCampaignActivityLog.
            $table->timestamp('created_at')->nullable();

            $table->unique(['template_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_template_versions');
    }
};
