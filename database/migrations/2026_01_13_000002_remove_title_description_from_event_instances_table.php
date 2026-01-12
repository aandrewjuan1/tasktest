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
        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('event_instances', 'overridden_title') ? 'overridden_title' : null,
            Schema::hasColumn('event_instances', 'overridden_description') ? 'overridden_description' : null,
        ]));

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('event_instances', function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_instances', function (Blueprint $table) {
            if (! Schema::hasColumn('event_instances', 'overridden_title')) {
                $table->string('overridden_title')->nullable()->after('status');
            }

            if (! Schema::hasColumn('event_instances', 'overridden_description')) {
                $table->text('overridden_description')->nullable()->after('overridden_title');
            }
        });
    }
};
