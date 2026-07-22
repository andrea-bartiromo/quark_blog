<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->nullable()->constrained('newsletters')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('ip_hash')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_opens');
    }
};
