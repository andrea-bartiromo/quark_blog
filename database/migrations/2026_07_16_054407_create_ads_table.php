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
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('position');
            $table->string('type');
            $table->boolean('active')->default(false);
            $table->unsignedInteger('priority')->default(0);
            $table->string('adsense_publisher_id', 50)->nullable();
            $table->string('adsense_slot_id', 20)->nullable();
            $table->string('adsense_format')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('banner_url', 500)->nullable();
            $table->string('banner_alt', 150)->nullable();
            $table->text('html_code')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // Query reale: Ad::forPosition() filtra su position + active e ordina per priority
            $table->index(['position', 'active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
