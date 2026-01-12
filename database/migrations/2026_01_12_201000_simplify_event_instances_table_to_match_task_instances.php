<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure any previous temp table is removed (safety for reruns)
        DB::statement('DROP TABLE IF EXISTS event_instances_new');

        // SQLite doesn't support ALTER TABLE for multiple column changes
        // We need to recreate the table with the simplified structure
        DB::statement("
            CREATE TABLE event_instances_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recurring_event_id INTEGER NOT NULL,
                event_id INTEGER,
                instance_date DATE NOT NULL,
                status VARCHAR CHECK (status IN ('scheduled', 'cancelled', 'completed', 'tentative', 'ongoing')) NOT NULL,
                cancelled TINYINT(1) NOT NULL DEFAULT '0',
                completed_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY(recurring_event_id) REFERENCES recurring_events(id) ON DELETE CASCADE,
                FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE SET NULL
            )
        ");

        // Copy data from old table to new table
        // Explicitly select only the columns we need (old table may have extra columns)
        DB::statement("
            INSERT INTO event_instances_new
            (id, recurring_event_id, event_id, instance_date, status, cancelled, completed_at, created_at, updated_at)
            SELECT
                id,
                recurring_event_id,
                event_id,
                DATE(instance_start) as instance_date,
                status,
                cancelled,
                completed_at,
                created_at,
                updated_at
            FROM event_instances
        ");

        // Drop old table
        Schema::drop('event_instances');

        // Rename new table
        DB::statement("ALTER TABLE event_instances_new RENAME TO event_instances");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS event_instances_new');

        // Revert to original structure
        DB::statement("
            CREATE TABLE event_instances_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recurring_event_id INTEGER NOT NULL,
                event_id INTEGER,
                instance_start DATETIME NOT NULL,
                instance_end DATETIME NOT NULL,
                status VARCHAR CHECK (status IN ('scheduled', 'cancelled', 'completed', 'tentative', 'ongoing')) NOT NULL,
                overridden_location VARCHAR,
                all_day TINYINT(1),
                timezone VARCHAR NOT NULL,
                cancelled TINYINT(1) NOT NULL DEFAULT '0',
                completed_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY(recurring_event_id) REFERENCES recurring_events(id) ON DELETE CASCADE,
                FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE SET NULL
            )
        ");

        // Copy data back (using instance_date for instance_start, and calculating instance_end)
        DB::statement("
            INSERT INTO event_instances_new
            (id, recurring_event_id, event_id, instance_start, instance_end, status, cancelled, completed_at, created_at, updated_at, timezone)
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
                'UTC' as timezone
            FROM event_instances
        ");

        // Drop current table
        Schema::drop('event_instances');

        // Rename new table
        DB::statement("ALTER TABLE event_instances_new RENAME TO event_instances");
    }
};
