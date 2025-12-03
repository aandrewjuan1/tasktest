<?php

declare(strict_types=1);

use App\Models\Project;
use App\Models\User;

test('user can soft delete their project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user);

    $project->delete();

    expect(Project::find($project->id))->toBeNull();
    expect(Project::withTrashed()->find($project->id))->not->toBeNull();
    expect(Project::withTrashed()->find($project->id)->trashed())->toBeTrue();
});

test('user can restore a soft deleted project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Restorable Project']);

    $project->delete();

    expect(Project::find($project->id))->toBeNull();
    expect(Project::withTrashed()->find($project->id)->trashed())->toBeTrue();

    $project->restore();

    expect(Project::find($project->id))->not->toBeNull();
    expect(Project::find($project->id)->trashed())->toBeFalse();
});

test('user can force delete a project permanently', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Permanently Deleted Project']);

    $projectId = $project->id;
    $project->forceDelete();

    expect(Project::find($projectId))->toBeNull();
    expect(Project::withTrashed()->find($projectId))->toBeNull();
});

test('soft deleted projects can be queried with withTrashed', function () {
    $user = User::factory()->create();

    $activeProject = Project::factory()->for($user)->create(['name' => 'Active']);
    $deletedProject = Project::factory()->for($user)->create(['name' => 'Deleted']);
    $deletedProject->delete();

    $allProjects = Project::withTrashed()->where('user_id', $user->id)->get();
    $onlyDeleted = Project::onlyTrashed()->where('user_id', $user->id)->get();

    expect($allProjects)->toHaveCount(2);
    expect($onlyDeleted)->toHaveCount(1);
    expect($onlyDeleted->first()->name)->toBe('Deleted');
});

test('projects page displays only non-deleted projects', function () {
    $user = User::factory()->create();

    $activeProject = Project::factory()->for($user)->create(['name' => 'Active Project']);
    $deletedProject = Project::factory()->for($user)->create(['name' => 'Deleted Project']);
    $deletedProject->delete();

    $projects = Project::where('user_id', $user->id)->get();

    expect($projects)->toHaveCount(1);
    expect($projects->first()->name)->toBe('Active Project');
});
