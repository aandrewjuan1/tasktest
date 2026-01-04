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
        Schema::table('tasks', function (Blueprint $table) {
            // Add new datetime columns
            $table->dateTime('start_datetime')->nullable()->after('duration');
            $table->dateTime('end_datetime')->nullable()->after('start_datetime');
        });

        // Migrate existing data: combine start_date + start_time → start_datetime
        DB::statement("
            UPDATE tasks
            SET start_datetime = CASE
                WHEN start_date IS NOT NULL AND start_time IS NOT NULL THEN
                    CONCAT(start_date, ' ', start_time)
                WHEN start_date IS NOT NULL THEN
                    CONCAT(start_date, ' 00:00:00')
                ELSE NULL
            END
        ");

        // Migrate existing data: end_date → end_datetime
        DB::statement("
            UPDATE tasks
            SET end_datetime = CASE
                WHEN end_date IS NOT NULL THEN
                    CONCAT(end_date, ' 00:00:00')
                ELSE NULL
            END
        ");

        // Drop old columns
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'start_time', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Add back old columns
            $table->date('start_date')->nullable()->after('duration');
            $table->time('start_time')->nullable()->after('start_date');
            $table->date('end_date')->nullable()->after('start_time');
        });

        // Migrate data back: start_datetime → start_date + start_time
        DB::statement("
            UPDATE tasks
            SET start_date = DATE(start_datetime),
                start_time = TIME(start_datetime)
            WHERE start_datetime IS NOT NULL
        ");

        // Migrate data back: end_datetime → end_date
        DB::statement("
            UPDATE tasks
            SET end_date = DATE(end_datetime)
            WHERE end_datetime IS NOT NULL
        ");

        // Drop new columns
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['start_datetime', 'end_datetime']);
        });
    }
};
