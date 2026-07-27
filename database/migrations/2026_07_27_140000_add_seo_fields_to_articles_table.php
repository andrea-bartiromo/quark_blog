<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Meta tag editoriali
            $table->string('seo_title')->nullable()->after('primary_sources');
            $table->text('seo_description')->nullable()->after('seo_title');

            // Canonical e indicizzazione
            $table->string('canonical_url', 2048)->nullable()->after('seo_description');
            $table->string('robots')->nullable()->after('canonical_url');

            // Open Graph
            $table->string('og_title')->nullable()->after('robots');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');

            // Twitter Card
            $table->string('twitter_title')->nullable()->after('og_image');
            $table->text('twitter_description')->nullable()->after('twitter_title');
            $table->string('twitter_image')->nullable()->after('twitter_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'canonical_url',
                'robots',
                'og_title',
                'og_description',
                'og_image',
                'twitter_title',
                'twitter_description',
                'twitter_image',
            ]);
        });
    }
};
