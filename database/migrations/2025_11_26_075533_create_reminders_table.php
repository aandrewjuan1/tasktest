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
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable'); // remindable_id and remindable_type (Task or Event)
            $table->enum('reminder_type', ['task_due', 'event_start', 'custom']);
            $table->timestamp('trigger_time');
            $table->enum('time_before_unit', ['minutes', 'hours', 'days'])->nullable();
            $table->integer('time_before_value')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
