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
        $taskType = 'App\\Models\\Task';
        $eventType = 'App\\Models\\Event';

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('taggable_id');
            $table->string('taggable_type');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_id', 'taggable_type'], 'taggables_unique');
            $table->index(['taggable_type', 'taggable_id'], 'taggables_type_id_index');
        });

        // Migrate existing task tags
        if (Schema::hasTable('tag_task')) {
            $taskRows = DB::table('tag_task')->get();

            foreach ($taskRows as $row) {
                DB::table('taggables')->insert([
                    'tag_id' => $row->tag_id,
                    'taggable_id' => $row->task_id,
                    'taggable_type' => $taskType,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        // Migrate existing event tags
        if (Schema::hasTable('tag_events')) {
            $eventRows = DB::table('tag_events')->get();

            foreach ($eventRows as $row) {
                DB::table('taggables')->insert([
                    'tag_id' => $row->tag_id,
                    'taggable_id' => $row->event_id,
                    'taggable_type' => $eventType,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('tag_task');
        Schema::dropIfExists('tag_events');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $taskType = 'App\\Models\\Task';
        $eventType = 'App\\Models\\Event';

        Schema::create('tag_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('tag_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('taggables')) {
            $taggables = DB::table('taggables')->get();

            foreach ($taggables as $row) {
                if ($row->taggable_type === $taskType) {
                    DB::table('tag_task')->insert([
                        'tag_id' => $row->tag_id,
                        'task_id' => $row->taggable_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }

                if ($row->taggable_type === $eventType) {
                    DB::table('tag_events')->insert([
                        'tag_id' => $row->tag_id,
                        'event_id' => $row->taggable_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            }
        }

        Schema::dropIfExists('taggables');
    }
};
