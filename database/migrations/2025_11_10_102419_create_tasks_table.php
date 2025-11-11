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
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('subject')->nullable();
            $table->string('type', 32)->default('assignment');
            $table->string('priority', 32)->default('medium');
            $table->string('status', 32)->default('to-do');
            $table->timestampTz('deadline')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'deadline']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
