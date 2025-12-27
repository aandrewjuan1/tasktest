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
        Schema::create('timegrid_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('start_hour')->default(6);
            $table->integer('end_hour')->default(22);
            $table->integer('hour_height')->default(60);
            $table->boolean('show_weekends')->default(true);
            $table->integer('default_event_duration')->default(30);
            $table->integer('slot_increment')->default(15);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timegrid_settings');
    }
};
