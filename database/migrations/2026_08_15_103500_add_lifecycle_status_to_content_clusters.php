<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_clusters', function (Blueprint $table) {
            $table->string('lifecycle_status', 20)->default('complete');
        });
    }

    public function down(): void
    {
        Schema::table('content_clusters', function (Blueprint $table) {
            $table->dropColumn('lifecycle_status');
        });
    }
};
