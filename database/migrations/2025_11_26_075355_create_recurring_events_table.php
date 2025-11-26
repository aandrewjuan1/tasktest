<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurring_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('recurrence_type', ['daily', 'weekly', 'monthly', 'yearly', 'custom']);
            $table->integer('interval');
            $table->string('days_of_week')->nullable();
            $table->integer('day_of_month')->nullable();
            $table->string('nth_weekday')->nullable();
            $table->text('rrule')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('occurrence_count')->nullable();
            $table->string('timezone');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_events');
    }
};
