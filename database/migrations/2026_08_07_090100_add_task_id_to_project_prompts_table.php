<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_prompts', function (Blueprint $table) {
            $table->foreignId('task_id')->nullable()->after('article_id')
                ->constrained('project_tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_prompts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_id');
        });
    }
};
