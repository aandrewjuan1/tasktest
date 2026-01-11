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
        Schema::table('recurring_tasks', function (Blueprint $table) {
            $table->datetime('start_datetime')->after('interval');
            $table->datetime('end_datetime')->after('start_datetime')->nullable();
        });

        // Copy data from date columns to datetime columns
        DB::statement('UPDATE recurring_tasks SET start_datetime = start_date, end_datetime = end_date WHERE start_date IS NOT NULL');

        Schema::table('recurring_tasks', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_tasks', function (Blueprint $table) {
            $table->date('start_date')->after('interval');
            $table->date('end_date')->after('start_date')->nullable();
        });

        // Copy data back from datetime columns to date columns
        DB::statement('UPDATE recurring_tasks SET start_date = DATE(start_datetime), end_date = DATE(end_datetime) WHERE start_datetime IS NOT NULL');

        Schema::table('recurring_tasks', function (Blueprint $table) {
            $table->dropColumn(['start_datetime', 'end_datetime']);
        });
    }
};
