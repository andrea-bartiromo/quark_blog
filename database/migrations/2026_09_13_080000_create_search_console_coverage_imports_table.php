<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_console_coverage_imports', function (Blueprint $table) {
            $table->id();
            $table->string('property', 255);
            $table->date('observed_at');
            $table->string('source', 80)->default('manual_csv');
            $table->string('source_filename', 255)->nullable();
            $table->string('import_batch', 64);
            $table->timestamp('imported_at');
            $table->timestamps();
            $table->unique(['property', 'observed_at'], 'sc_coverage_property_date_unique');
        });

        Schema::create('search_console_coverage_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coverage_import_id')->constrained('search_console_coverage_imports')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->string('source', 120)->nullable();
            $table->string('validation', 120)->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->string('page_url', 500)->nullable();
            $table->json('audit')->nullable();
            $table->string('classification', 40);
            $table->string('recommendation', 500);
            $table->timestamps();
            $table->index(['coverage_import_id', 'classification'], 'sc_coverage_import_class_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_coverage_issues');
        Schema::dropIfExists('search_console_coverage_imports');
    }
};
