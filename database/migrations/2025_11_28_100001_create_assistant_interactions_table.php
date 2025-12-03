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
        Schema::create('assistant_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('interaction_type', [
                'smart_prioritize',
                'smart_schedule',
                'chat',
                'summary',
                'automation',
            ]);
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('prompt_snapshot')->nullable();
            $table->json('response_data')->nullable();
            $table->text('reasoning_snippet')->nullable();
            $table->string('model_used')->default('hermes3:3b');
            $table->integer('tokens_used')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('interaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_interactions');
    }
};
