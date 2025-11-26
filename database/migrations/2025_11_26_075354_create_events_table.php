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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestampTz('start_datetime');
            $table->timestampTz('end_datetime');
            $table->boolean('all_day')->default(false);
            $table->string('timezone');
            $table->string('location')->nullable();
            $table->string('color')->nullable();
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative'])->nullable();
            $table->foreignId('recurring_event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
