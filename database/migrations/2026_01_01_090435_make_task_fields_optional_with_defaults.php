<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Make status nullable with default
            $table->enum('status', ['to_do', 'doing', 'done'])
                ->default('to_do')
                ->nullable()
                ->change();

            // Make priority nullable with default
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                ->default('medium')
                ->nullable()
                ->change();

            // Make complexity nullable with default
            $table->enum('complexity', ['simple', 'moderate', 'complex'])
                ->default('moderate')
                ->nullable()
                ->change();

            // Make dates nullable
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['to_do', 'doing', 'done'])
                ->default('to_do')
                ->nullable(false)
                ->change();

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                ->default('medium')
                ->nullable(false)
                ->change();

            $table->enum('complexity', ['simple', 'moderate', 'complex'])
                ->default('moderate')
                ->nullable(false)
                ->change();

            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
