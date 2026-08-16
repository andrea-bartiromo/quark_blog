<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_clusters', function (Blueprint $table) {
            $table->json('takeaways')->nullable();
            $table->json('guiding_questions')->nullable();
            $table->string('closing_title')->nullable();
            $table->text('closing_text')->nullable();
        });

        Schema::table('article_content_cluster', function (Blueprint $table) {
            $table->text('transition_text')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('article_content_cluster', function (Blueprint $table) {
            $table->dropColumn('transition_text');
        });

        Schema::table('content_clusters', function (Blueprint $table) {
            $table->dropColumn(['takeaways', 'guiding_questions', 'closing_title', 'closing_text']);
        });
    }
};
