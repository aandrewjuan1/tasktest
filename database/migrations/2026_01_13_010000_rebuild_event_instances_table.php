<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP TABLE IF EXISTS event_instances_new');

        Schema::create('event_instances_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->date('instance_date');
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative', 'ongoing']);
            $table->boolean('cancelled')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        $hasInstanceDate = Schema::hasColumn('event_instances', 'instance_date');
        $instanceDateExpression = $hasInstanceDate ? 'instance_date' : "DATE(instance_start)";

        DB::statement("
            INSERT INTO event_instances_new
                (id, recurring_event_id, event_id, instance_date, status, cancelled, completed_at, created_at, updated_at)
            SELECT
                id,
                recurring_event_id,
                event_id,
                {$instanceDateExpression} as instance_date,
                status,
                cancelled,
                completed_at,
                created_at,
                updated_at
            FROM event_instances
        ");

        Schema::drop('event_instances');

        DB::statement("ALTER TABLE event_instances_new RENAME TO event_instances");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS event_instances_new');

        Schema::create('event_instances_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('instance_start');
            $table->timestampTz('instance_end');
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative', 'ongoing']);
            $table->string('overridden_title')->nullable();
            $table->text('overridden_description')->nullable();
            $table->string('overridden_location')->nullable();
            $table->boolean('all_day')->nullable();
            $table->string('timezone')->default('UTC');
            $table->boolean('cancelled')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        DB::statement("
            INSERT INTO event_instances_new
                (id, recurring_event_id, event_id, instance_start, instance_end, status, cancelled, completed_at, created_at, updated_at, timezone, overridden_title, overridden_description, overridden_location, all_day)
            SELECT
                id,
                recurring_event_id,
                event_id,
                DATETIME(instance_date, '00:00:00') as instance_start,
                DATETIME(instance_date, '01:00:00') as instance_end,
                status,
                cancelled,
                completed_at,
                created_at,
                updated_at,
                'UTC' as timezone,
                NULL as overridden_title,
                NULL as overridden_description,
                NULL as overridden_location,
                NULL as all_day
            FROM event_instances
        ");

        Schema::drop('event_instances');

        DB::statement("ALTER TABLE event_instances_new RENAME TO event_instances");
    }
};
