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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('reminder_notifications_enabled')->default(true);
            $table->boolean('task_due_notifications_enabled')->default(true);
            $table->boolean('event_start_notifications_enabled')->default(true);
            $table->boolean('pomodoro_notifications_enabled')->default(true);
            $table->boolean('achievement_notifications_enabled')->default(true);
            $table->boolean('system_notifications_enabled')->default(true);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('push_enabled')->default(false);
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->enum('notification_frequency', ['immediate', 'hourly', 'daily', 'weekly'])->default('immediate');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
