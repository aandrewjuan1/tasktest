<?php

namespace App\Observers;

use App\Events\ProjectCreated;
use App\Events\ProjectDeleted;
use App\Events\ProjectUpdated;
use App\Models\Project;

class ProjectObserver
{
    public function created(Project $project): void
    {
        ProjectCreated::dispatch($project);
    }

    public function updated(Project $project): void
    {
        ProjectUpdated::dispatch($project);
    }

    public function deleted(Project $project): void
    {
        ProjectDeleted::dispatch($project);
    }
}
