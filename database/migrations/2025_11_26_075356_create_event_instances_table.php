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
        Schema::create('event_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('instance_start');
            $table->timestampTz('instance_end');
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative']);
            $table->string('overridden_title')->nullable();
            $table->text('overridden_description')->nullable();
            $table->string('overridden_location')->nullable();
            $table->boolean('all_day')->nullable();
            $table->string('timezone');
            $table->boolean('cancelled')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_instances');
    }
};
