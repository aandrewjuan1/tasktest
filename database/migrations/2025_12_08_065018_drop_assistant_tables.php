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
        Schema::dropIfExists('assistant_tool_executions');
        Schema::dropIfExists('assistant_feedback');
        Schema::dropIfExists('assistant_messages');
        Schema::dropIfExists('assistant_interactions');
        Schema::dropIfExists('assistant_conversations');
        Schema::dropIfExists('assistant_schemas');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tables cannot be recreated without the original migration files
        // This migration is intended to be one-way only
    }
};
