<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('email');
            $table->string('photo')->nullable()->after('bio');
            $table->string('role')->default('author')->after('photo');
            $table->string('twitter')->nullable()->after('role');
            $table->string('linkedin')->nullable()->after('twitter');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'photo', 'role', 'twitter', 'linkedin']);
        });
    }
};
