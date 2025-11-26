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
        Schema::create('pomodoro_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->integer('work_duration_minutes')->default(25);
            $table->integer('break_duration_minutes')->default(5);
            $table->integer('long_break_duration_minutes')->default(15);
            $table->integer('cycles_before_long_break')->default(4);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('auto_start_next_session')->default(false);
            $table->boolean('auto_start_break')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pomodoro_settings');
    }
};
