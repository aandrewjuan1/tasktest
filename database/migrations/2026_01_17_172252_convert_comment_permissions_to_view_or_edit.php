<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert all 'comment' permissions to 'view'
        // This is a safe default - users with comment permission can view but not edit
        // They can be upgraded to 'edit' permission later if needed
        DB::table('collaborations')
            ->where('permission', 'comment')
            ->update(['permission' => 'view']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: We cannot reliably reverse this migration as we don't know
        // which 'view' permissions were originally 'comment' permissions
        // This is a one-way migration
    }
};
