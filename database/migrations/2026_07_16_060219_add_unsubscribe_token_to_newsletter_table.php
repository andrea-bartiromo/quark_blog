<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Nullable: Newsletter::subscribe() is the only place that creates rows
        // and it always sets this column, but SQLite can't add a NOT NULL
        // column without a table rebuild, so we keep it nullable and rely on
        // the application (and the unique index below) instead.
        Schema::table('newsletter', function (Blueprint $table) {
            $table->string('unsubscribe_token', 32)->nullable()->after('token');
        });

        $existing = DB::table('newsletter')->whereNull('unsubscribe_token')->pluck('id');
        $used = [];

        foreach ($existing as $id) {
            do {
                $token = Str::random(32);
            } while (isset($used[$token]));

            $used[$token] = true;

            DB::table('newsletter')->where('id', $id)->update(['unsubscribe_token' => $token]);
        }

        Schema::table('newsletter', function (Blueprint $table) {
            $table->unique('unsubscribe_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletter', function (Blueprint $table) {
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn('unsubscribe_token');
        });
    }
};
