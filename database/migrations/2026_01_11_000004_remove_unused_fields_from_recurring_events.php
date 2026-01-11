<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_events', function (Blueprint $table) {
            $table->dropColumn([
                'day_of_month',
                'nth_weekday',
                'rrule',
                'occurrence_count',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('recurring_events', function (Blueprint $table) {
            $table->integer('day_of_month')->nullable()->after('days_of_week');
            $table->string('nth_weekday')->nullable()->after('day_of_month');
            $table->text('rrule')->nullable()->after('nth_weekday');
            $table->integer('occurrence_count')->nullable()->after('end_datetime');
        });
    }
};
