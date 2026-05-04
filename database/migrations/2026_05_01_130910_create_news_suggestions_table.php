<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('source_title');           // Titolo dalla fonte esterna
            $table->string('source_url');             // URL fonte originale
            $table->string('source_name');            // Nome del sito fonte
            $table->text('source_excerpt')->nullable(); // Estratto dalla fonte
            $table->string('category');               // Categoria suggerita
            $table->string('generated_title')->nullable();   // Titolo generato da AI
            $table->text('generated_excerpt')->nullable();   // Estratto generato da AI
            $table->longText('generated_body')->nullable();  // Corpo articolo generato da AI
            $table->enum('status', ['pending', 'approved', 'rejected', 'published'])->default('pending');
            $table->foreignId('article_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_suggestions');
    }
};
