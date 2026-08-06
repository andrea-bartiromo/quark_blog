<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('task');

            $table->string('manual_status')->default('todo');
            $table->string('derived_status')->nullable();
            $table->string('status_source')->default('manual');
            $table->boolean('manual_override')->default(false);

            $table->string('priority')->default('medium');
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Nessun vincolo FK reale: un articolo eliminato deve poter
            // lasciare il riferimento "pendente" (invalid_link) invece di
            // essere annullato o bloccato dal DB — vedi ProjectTaskSyncService.
            $table->unsignedBigInteger('article_id')->nullable();
            $table->index('article_id');

            $table->foreignId('depends_on_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('duplicated_from_id')->nullable()->constrained('project_tasks')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('manual_status');
            $table->index('derived_status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
