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
        Schema::create('assistant_tool_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('assistant_messages')->cascadeOnDelete();
            $table->foreignId('interaction_id')->nullable()->constrained('assistant_interactions')->cascadeOnDelete();
            $table->string('tool_name');
            $table->json('input_parameters')->nullable();
            $table->json('output_result')->nullable();
            $table->enum('execution_status', ['pending', 'success', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['message_id', 'executed_at']);
            $table->index(['interaction_id', 'executed_at']);
            $table->index('tool_name');
            $table->index('execution_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_tool_executions');
    }
};
