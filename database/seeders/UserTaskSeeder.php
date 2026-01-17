<?php

namespace Database\Seeders;

use App\Enums\CollaborationPermission;
use App\Models\Collaboration;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create 10 random users
            $randomUsers = User::factory()->count(10)->create();

            // Create or get the user with the specified email
            $andrewUser = User::firstOrCreate(
                ['email' => 'andrew.juan.cvt@eac.edu.ph'],
                [
                    'name' => 'Andrew Juan',
                    'workos_id' => \Illuminate\Support\Str::uuid()->toString(),
                    'avatar' => fake()->imageUrl(),
                ]
            );

            // Combine all users into a collection
            $allUsers = $randomUsers->push($andrewUser);

            // For each user, create 5 tasks
            foreach ($allUsers as $user) {
                $tasks = Task::factory()->count(5)->create([
                    'user_id' => $user->id,
                    'start_datetime' => null,
                    'end_datetime' => null,
                ]);

                // Randomly select 3 tasks to add collaborators
                $tasksWithCollaborators = $tasks->random(min(3, $tasks->count()));

                foreach ($tasksWithCollaborators as $task) {
                    // Randomly select 1-3 collaborators from the pool (excluding task owner)
                    $availableCollaborators = $allUsers->reject(fn ($u) => $u->id === $user->id);
                    $numCollaborators = fake()->numberBetween(1, min(3, $availableCollaborators->count()));

                    if ($numCollaborators > 0) {
                        $collaborators = $availableCollaborators->random($numCollaborators);

                        foreach ($collaborators as $collaborator) {
                            // Assign random permission
                            $permission = fake()->randomElement([
                                CollaborationPermission::View,
                                CollaborationPermission::Edit,
                            ]);

                            Collaboration::create([
                                'collaboratable_type' => Task::class,
                                'collaboratable_id' => $task->id,
                                'user_id' => $collaborator->id,
                                'permission' => $permission,
                            ]);
                        }
                    }
                }
            }
        });
    }
}
