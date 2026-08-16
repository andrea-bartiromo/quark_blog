<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_content_cluster', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_cluster_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['article_id', 'content_cluster_id'], 'article_cluster_unique');
            $table->index(['content_cluster_id', 'position'], 'cluster_position_index');
            $table->index(['article_id', 'is_primary'], 'article_primary_cluster_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_content_cluster');
    }
};
