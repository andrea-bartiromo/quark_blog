<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('event_key', 191);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('remote_id', 191)->nullable();
            $table->string('remote_url', 2048)->nullable();
            $table->string('last_error_class', 191)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'channel', 'event_key'], 'social_publications_logical_unique');
            $table->index(['status', 'channel'], 'social_publications_status_channel_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_publications');
    }
};
