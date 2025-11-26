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
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['to_do', 'doing', 'done']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent']);
            $table->enum('complexity', ['simple', 'moderate', 'complex']);
            $table->integer('duration'); // duration in minutes
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
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
