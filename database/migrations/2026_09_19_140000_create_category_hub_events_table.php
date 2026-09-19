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
        Schema::create('category_hub_events', function (Blueprint $table) {
            $table->id();

            // 'impression' | 'click_through', vedi CategoryHubEvent — stesso
            // pattern di article_continuation_events (Growth S2).
            $table->string('event_type');

            // Slug testuale, non una foreign key verso categories: una
            // categoria legacy solo-config (nessuna riga DB, vedi
            // Category::publicOptions()) deve poter registrare eventi
            // esattamente come una categoria con riga DB.
            $table->string('category_slug');

            $table->timestamp('created_at')->nullable();

            $table->index(['category_slug', 'event_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_hub_events');
    }
};
