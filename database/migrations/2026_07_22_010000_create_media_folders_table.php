<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->string('path')->unique();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('media_folders')
                ->restrictOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_protected')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('description', 500)->nullable();
            $table->string('icon', 50)->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};
