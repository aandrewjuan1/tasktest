<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if columns exist before proceeding
        $hasTitle = Schema::hasColumn('task_instances', 'overridden_title');
        $hasDescription = Schema::hasColumn('task_instances', 'overridden_description');

        if (! $hasTitle && ! $hasDescription) {
            return; // Columns already removed
        }

        // SQLite doesn't reliably support DROP COLUMN, so we recreate the table
        DB::statement('DROP TABLE IF EXISTS task_instances_new');

        DB::statement("
            CREATE TABLE task_instances_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recurring_task_id INTEGER NOT NULL,
                task_id INTEGER,
                instance_date DATE NOT NULL,
                status VARCHAR CHECK (status IN ('to_do', 'doing', 'done')) NOT NULL,
                completed_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY(recurring_task_id) REFERENCES recurring_tasks(id) ON DELETE CASCADE,
                FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE SET NULL
            )
        ");

        // Copy data from old table to new table (excluding removed columns)
        DB::statement("
            INSERT INTO task_instances_new
            (id, recurring_task_id, task_id, instance_date, status, completed_at, created_at, updated_at)
            SELECT
                id,
                recurring_task_id,
                task_id,
                instance_date,
                status,
                completed_at,
                created_at,
                updated_at
            FROM task_instances
        ");

        // Drop old table
        Schema::drop('task_instances');

        // Rename new table
        DB::statement('ALTER TABLE task_instances_new RENAME TO task_instances');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS task_instances_new');

        // Revert to original structure with overridden columns
        DB::statement("
            CREATE TABLE task_instances_new (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                recurring_task_id INTEGER NOT NULL,
                task_id INTEGER,
                instance_date DATE NOT NULL,
                status VARCHAR CHECK (status IN ('to_do', 'doing', 'done')) NOT NULL,
                overridden_title VARCHAR,
                overridden_description TEXT,
                completed_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY(recurring_task_id) REFERENCES recurring_tasks(id) ON DELETE CASCADE,
                FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE SET NULL
            )
        ");

        // Copy data back (with NULL for overridden columns)
        DB::statement("
            INSERT INTO task_instances_new
            (id, recurring_task_id, task_id, instance_date, status, overridden_title, overridden_description, completed_at, created_at, updated_at)
            SELECT
                id,
                recurring_task_id,
                task_id,
                instance_date,
                status,
                NULL as overridden_title,
                NULL as overridden_description,
                completed_at,
                created_at,
                updated_at
            FROM task_instances
        ");

        // Drop current table
        Schema::drop('task_instances');

        // Rename new table
        DB::statement('ALTER TABLE task_instances_new RENAME TO task_instances');
    }
};
