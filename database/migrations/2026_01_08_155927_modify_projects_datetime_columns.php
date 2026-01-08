<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Rename and change column types from date to datetime
            $table->datetime('start_datetime')->nullable()->after('description');
            $table->datetime('end_datetime')->nullable()->after('start_datetime');
        });

        // Copy data from old columns to new columns
        DB::statement('UPDATE projects SET start_datetime = start_date WHERE start_date IS NOT NULL');
        DB::statement('UPDATE projects SET end_datetime = end_date WHERE end_date IS NOT NULL');

        Schema::table('projects', function (Blueprint $table) {
            // Drop old date columns
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Recreate old date columns
            $table->date('start_date')->nullable()->after('description');
            $table->date('end_date')->nullable()->after('start_date');
        });

        // Copy data from datetime columns back to date columns (losing time information)
        DB::statement('UPDATE projects SET start_date = DATE(start_datetime) WHERE start_datetime IS NOT NULL');
        DB::statement('UPDATE projects SET end_date = DATE(end_datetime) WHERE end_datetime IS NOT NULL');

        Schema::table('projects', function (Blueprint $table) {
            // Drop datetime columns
            $table->dropColumn(['start_datetime', 'end_datetime']);
        });
    }
};
