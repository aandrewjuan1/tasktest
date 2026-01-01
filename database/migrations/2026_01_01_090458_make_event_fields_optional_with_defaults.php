<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Make end_datetime nullable
            $table->timestampTz('end_datetime')->nullable()->change();

            // Make timezone nullable
            $table->string('timezone')->nullable()->change();

            // Add default status
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative'])
                ->default('scheduled')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestampTz('end_datetime')->nullable(false)->change();
            $table->string('timezone')->nullable(false)->change();
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative'])
                ->default('scheduled')
                ->nullable(false)
                ->change();
        });
    }
};
