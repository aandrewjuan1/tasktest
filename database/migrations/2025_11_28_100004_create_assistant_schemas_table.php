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
        Schema::create('assistant_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('schema_name')->unique();
            $table->enum('schema_type', [
                'prioritization',
                'scheduling',
                'summary',
                'action_confirmation',
                'tool_definition',
            ]);
            $table->json('json_schema');
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['schema_type', 'is_active']);
            $table->index('schema_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_schemas');
    }
};
