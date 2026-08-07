<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('github_branch')->nullable()->after('duplicated_from_id');
            $table->unsignedInteger('github_pr_number')->nullable()->after('github_branch');
            $table->string('github_pr_state')->nullable()->after('github_pr_number');
            $table->string('github_checks_state')->nullable()->after('github_pr_state');
            $table->string('github_review_state')->nullable()->after('github_checks_state');
            $table->timestamp('github_synced_at')->nullable()->after('github_review_state');

            $table->index('github_branch');
            $table->index('github_pr_state');
        });
    }

    public function down(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex(['github_branch']);
            $table->dropIndex(['github_pr_state']);

            $table->dropColumn([
                'github_branch',
                'github_pr_number',
                'github_pr_state',
                'github_checks_state',
                'github_review_state',
                'github_synced_at',
            ]);
        });
    }
};
