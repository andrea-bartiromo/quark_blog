<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_clusters', function (Blueprint $table) {
            $table->foreignId('pillar_article_id')
                ->nullable()
                ->after('seo_description')
                ->constrained('articles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('content_clusters', function (Blueprint $table) {
            $table->dropForeign(['pillar_article_id']);
            $table->dropColumn('pillar_article_id');
        });
    }
};
