<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('article_revisions', function (Blueprint $table) {
            $table->string('excerpt')->nullable()->change();
        });
    }
};
