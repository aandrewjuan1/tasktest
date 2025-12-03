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
        Schema::create('assistant_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feedbackable_type');
            $table->unsignedBigInteger('feedbackable_id');
            $table->enum('rating', ['thumbs_up', 'thumbs_down', 'neutral'])->nullable();
            $table->text('feedback_text')->nullable();
            $table->text('improvement_suggestion')->nullable();
            $table->timestamps();

            $table->index(['feedbackable_type', 'feedbackable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_feedback');
    }
};
