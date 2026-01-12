<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Project;
use App\Models\Task;
use App\Observers\EventObserver;
use App\Observers\ProjectObserver;
use App\Observers\TaskObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Task::observe(TaskObserver::class);
        Event::observe(EventObserver::class);
        Project::observe(ProjectObserver::class);
    }
}
