<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite doesn't support ALTER TABLE for CHECK constraints directly
        // We need to recreate the table with the updated constraint
        DB::statement("
            CREATE TABLE event_instances_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recurring_event_id INTEGER NOT NULL,
                event_id INTEGER,
                instance_start DATETIME NOT NULL,
                instance_end DATETIME NOT NULL,
                status VARCHAR CHECK (status IN ('scheduled', 'cancelled', 'completed', 'tentative', 'ongoing')) NOT NULL,
                overridden_title VARCHAR,
                overridden_description TEXT,
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

        // Copy data from old table to new table
        DB::statement("
            INSERT INTO event_instances_new
            SELECT * FROM event_instances
        ");

        // Drop old table
        Schema::drop('event_instances');

        // Rename new table
        DB::statement("ALTER TABLE event_instances_new RENAME TO event_instances");
    }

    public function down(): void
    {
        // Revert to original enum values
        DB::statement("
            CREATE TABLE event_instances_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recurring_event_id INTEGER NOT NULL,
                event_id INTEGER,
                instance_start DATETIME NOT NULL,
                instance_end DATETIME NOT NULL,
                status VARCHAR CHECK (status IN ('scheduled', 'cancelled', 'completed', 'tentative')) NOT NULL,
                overridden_title VARCHAR,
                overridden_description TEXT,
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

        // Copy data (excluding any 'ongoing' status records)
        DB::statement("
            INSERT INTO event_instances_new
            SELECT * FROM event_instances
            WHERE status != 'ongoing'
        ");

        // Drop old table
        Schema::drop('event_instances');

        // Rename new table
        DB::statement("ALTER TABLE event_instances_new RENAME TO event_instances");
    }
};
