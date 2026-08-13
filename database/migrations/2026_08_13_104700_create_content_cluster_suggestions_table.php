<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_cluster_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_cluster_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedTinyInteger('confidence');
            $table->json('reasons');
            $table->char('evidence_hash', 64);
            $table->boolean('suggested_primary')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'content_cluster_id'], 'cluster_suggestion_unique');
            $table->index(['status', 'confidence'], 'cluster_suggestion_status_confidence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_cluster_suggestions');
    }
};
