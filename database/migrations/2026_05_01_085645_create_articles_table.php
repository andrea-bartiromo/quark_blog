<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('category');
            $table->string('cover_image')->nullable();
            $table->enum('status', ['draft', 'published', 'review'])->default('draft');
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('read_minutes')->default(5);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('category');
            $table->index('status');
            $table->index('featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
