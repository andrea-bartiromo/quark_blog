<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_definition')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->timestamps();
        });

        Schema::create('concept_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->timestamps();

            $table->unique(['concept_id', 'alias']);
            $table->index('alias');
        });

        Schema::create('article_concepts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->string('relation_type', 24)->default('supporting');
            $table->unsignedTinyInteger('weight')->default(50);
            $table->timestamps();

            $table->unique(['article_id', 'concept_id']);
            $table->index(['concept_id', 'relation_type', 'weight'], 'article_concepts_lookup_idx');
        });

        Schema::create('concept_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('concept_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->string('slug')->unique();
            $table->text('answer_summary')->nullable();
            $table->foreignId('target_article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 24)->default('draft');
            $table->timestamps();

            $table->index(['concept_id', 'status', 'sort_order'], 'concept_questions_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_questions');
        Schema::dropIfExists('article_concepts');
        Schema::dropIfExists('concept_aliases');
        Schema::dropIfExists('concepts');
    }
};
