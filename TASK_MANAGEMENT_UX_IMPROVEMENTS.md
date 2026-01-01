# Task Management UX Improvements - AI Agent Implementation Guide

## Context & Purpose

**Objective**: Refactor the task management system to implement "quick capture" principles, allowing users to create tasks, events, and projects with minimal friction (only title required).

**Principle**: Users should be able to create a task in 2-3 seconds with only a title. All other fields should be optional with sensible defaults.

**Impact**: Reduces task creation time from 30-45 seconds to 2-3 seconds, improving mobile experience and reducing user friction.

---

## Implementation Order

**CRITICAL**: Follow this order exactly:

1. Database Migrations (must be first)
2. Model Updates (after migrations)
3. Form Request Updates
4. Livewire Volt Components
5. Display Components
6. Testing

---

## PHASE 1: Database Migrations

### TASK 1.1: Create Task Fields Migration

**File**: `database/migrations/[timestamp]_make_task_fields_optional_with_defaults.php`

**Action**: Create new migration file

**Command**:
```bash
php artisan make:migration make_task_fields_optional_with_defaults
```

**Migration Content**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Make status nullable with default
            $table->enum('status', ['to_do', 'doing', 'done'])
                ->default('to_do')
                ->nullable()
                ->change();

            // Make priority nullable with default
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                ->default('medium')
                ->nullable()
                ->change();

            // Make complexity nullable with default
            $table->enum('complexity', ['simple', 'moderate', 'complex'])
                ->default('moderate')
                ->nullable()
                ->change();

            // Make dates nullable
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['to_do', 'doing', 'done'])
                ->default('to_do')
                ->nullable(false)
                ->change();

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                ->default('medium')
                ->nullable(false)
                ->change();

            $table->enum('complexity', ['simple', 'moderate', 'complex'])
                ->default('moderate')
                ->nullable(false)
                ->change();

            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
```

---

### TASK 1.2: Create Event Fields Migration

**File**: `database/migrations/[timestamp]_make_event_fields_optional_with_defaults.php`

**Action**: Create new migration file

**Command**:
```bash
php artisan make:migration make_event_fields_optional_with_defaults
```

**Migration Content**:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Make end_datetime nullable
            $table->timestampTz('end_datetime')->nullable()->change();

            // Make timezone nullable
            $table->string('timezone')->nullable()->change();

            // Add default status
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative'])
                ->default('scheduled')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestampTz('end_datetime')->nullable(false)->change();
            $table->string('timezone')->nullable(false)->change();
            $table->enum('status', ['scheduled', 'cancelled', 'completed', 'tentative'])
                ->default('scheduled')
                ->nullable(false)
                ->change();
        });
    }
};
```

---

## PHASE 2: Model Updates

### TASK 2.1: Update Task Model

**File**: `app/Models/Task.php`

**Action**: Add default values using model events in `booted()` method

**Location**: Add after the `casts()` method, before relationships

**Code to Add**:
```php
protected static function booted(): void
{
    static::creating(function (Task $task) {
        // Set defaults if null
        if (is_null($task->status)) {
            $task->status = TaskStatus::ToDo;
        }
        if (is_null($task->priority)) {
            $task->priority = TaskPriority::Medium;
        }
        if (is_null($task->complexity)) {
            $task->complexity = TaskComplexity::Moderate;
        }
    });
}
```

**Required Imports** (if not already present):
```php
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskComplexity;
```

---

### TASK 2.2: Update Event Model

**File**: `app/Models/Event.php`

**Action**: Add default values and auto-calculation in `booted()` method

**Location**: Add after the `casts()` method, before relationships

**Code to Add**:
```php
protected static function booted(): void
{
    static::creating(function (Event $event) {
        // Set default status
        if (is_null($event->status)) {
            $event->status = EventStatus::Scheduled;
        }

        // Auto-calculate end_datetime if not provided
        if (is_null($event->end_datetime) && $event->start_datetime) {
            $event->end_datetime = $event->start_datetime->copy()->addHour();
        }

        // Set timezone from user if not provided
        if (is_null($event->timezone) && auth()->check()) {
            $event->timezone = auth()->user()->timezone ?? config('app.timezone');
        }
    });
}
```

**Required Imports** (if not already present):
```php
use App\Enums\EventStatus;
use Carbon\Carbon;
```

---

## PHASE 3: Form Request Updates

### TASK 3.1: Update TaskStoreRequest

**File**: `app/Http/Requests/TaskStoreRequest.php`

**Action**: Change `status` from `required` to `nullable` in validation rules

**Find**:
```php
'status' => ['required', Rule::enum(TaskStatus::class)],
```

**Replace With**:
```php
'status' => ['nullable', Rule::enum(TaskStatus::class)],
```

**Note**: `priority`, `complexity`, `start_date`, and `end_date` should already be nullable. Verify they are.

---

### TASK 3.2: Update EventStoreRequest

**File**: `app/Http/Requests/EventStoreRequest.php`

**Action**: Make `end_datetime` and `timezone` nullable

**Find**:
```php
'end_datetime' => ['required', 'date', 'after:start_datetime'],
'timezone' => ['required', 'string'],
```

**Replace With**:
```php
'end_datetime' => ['nullable', 'date', 'after:start_datetime'],
'timezone' => ['nullable', 'string'],
```

**Also Update** (if exists):
```php
'status' => ['nullable', Rule::enum(EventStatus::class)],
```

---

## PHASE 4: Livewire Volt Components

### TASK 4.1: Update create-item-modal.blade.php - Task Form

**File**: `resources/views/livewire/workspace/create-item-modal.blade.php`

#### SUBTASK 4.1.1: Remove Date Defaults in mount()

**Find**:
```php
public function mount(): void
{
    $this->taskStartDate = now()->format('Y-m-d');
    $this->taskEndDate = now()->addWeek()->format('Y-m-d');
}
```

**Replace With**:
```php
public function mount(): void
{
    // Don't set dates by default - let them be optional
}
```

#### SUBTASK 4.1.2: Update resetForm() Method

**Find**:
```php
$this->taskStartDate = now()->format('Y-m-d');
$this->taskEndDate = now()->addWeek()->format('Y-m-d');
```

**Replace With**:
```php
$this->taskStartDate = '';
$this->taskEndDate = '';
```

#### SUBTASK 4.1.3: Update createTask() Validation

**Find**:
```php
$validated = $this->validate([
    'taskTitle' => 'required|string|max:255',
    'taskDescription' => 'nullable|string',
    'taskStatus' => 'required|string|in:to_do,doing,done',
    'taskPriority' => 'required|string|in:low,medium,high,urgent',
    'taskComplexity' => 'required|string|in:simple,moderate,complex',
    'taskDuration' => 'nullable|integer|min:1',
    'taskStartDate' => 'required|date',
    'taskStartTime' => 'nullable|date_format:H:i',
    'taskEndDate' => 'required|date|after_or_equal:taskStartDate',
    'taskProjectId' => 'nullable|exists:projects,id',
], [], [
    // ... attribute names ...
]);
```

**Replace With**:
```php
$validated = $this->validate([
    'taskTitle' => 'required|string|max:255',
    'taskDescription' => 'nullable|string',
    'taskStatus' => 'nullable|string|in:to_do,doing,done',
    'taskPriority' => 'nullable|string|in:low,medium,high,urgent',
    'taskComplexity' => 'nullable|string|in:simple,moderate,complex',
    'taskDuration' => 'nullable|integer|min:1',
    'taskStartDate' => 'nullable|date',
    'taskStartTime' => 'nullable|date_format:H:i',
    'taskEndDate' => 'nullable|date|after_or_equal:taskStartDate',
    'taskProjectId' => 'nullable|exists:projects,id',
], [], [
    'taskTitle' => 'title',
    'taskDescription' => 'description',
    'taskStatus' => 'status',
    'taskPriority' => 'priority',
    'taskComplexity' => 'complexity',
    'taskDuration' => 'duration',
    'taskStartDate' => 'start date',
    'taskStartTime' => 'start time',
    'taskEndDate' => 'end date',
    'taskProjectId' => 'project',
]);
```

#### SUBTASK 4.1.4: Update createTask() Task::create() Call

**Find**:
```php
Task::create([
    'user_id' => auth()->id(),
    'title' => $this->taskTitle,
    'description' => $this->taskDescription ?: null,
    'status' => $this->taskStatus,
    'priority' => $this->taskPriority,
    'complexity' => $this->taskComplexity,
    'duration' => $this->taskDuration ?: null,
    'start_date' => $this->taskStartDate,
    'start_time' => $startTime,
    'end_date' => $this->taskEndDate,
    'project_id' => $this->taskProjectId ?: null,
]);
```

**Replace With**:
```php
Task::create([
    'user_id' => auth()->id(),
    'title' => $this->taskTitle,
    'description' => $this->taskDescription ?: null,
    'status' => $this->taskStatus ?: null,
    'priority' => $this->taskPriority ?: null,
    'complexity' => $this->taskComplexity ?: null,
    'duration' => $this->taskDuration ?: null,
    'start_date' => $this->taskStartDate ?: null,
    'start_time' => $startTime,
    'end_date' => $this->taskEndDate ?: null,
    'project_id' => $this->taskProjectId ?: null,
]);
```

#### SUBTASK 4.1.5: Update Blade Template - Remove Required Attributes

**Find** (in task form section):
```blade
<flux:select wire:model="taskStatus" label="Status" required>
```

**Replace With**:
```blade
<flux:select wire:model="taskStatus" label="Status">
    <option value="">Select Status</option>
```

**Find**:
```blade
<flux:input wire:model="taskStartDate" label="Start Date" type="date" required />
<flux:input wire:model="taskEndDate" label="Due Date" type="date" required />
```

**Replace With**:
```blade
<flux:input wire:model="taskStartDate" label="Start Date (optional)" type="date" />
<flux:input wire:model="taskEndDate" label="Due Date (optional)" type="date" />
```

---

### TASK 4.2: Update create-item-modal.blade.php - Event Form

**File**: `resources/views/livewire/workspace/create-item-modal.blade.php`

#### SUBTASK 4.2.1: Update createEvent() Validation

**Find**:
```php
$validated = $this->validate([
    'eventTitle' => 'required|string|max:255',
    'eventDescription' => 'nullable|string',
    'eventStartDatetime' => 'required|date',
    'eventEndDatetime' => 'required|date|after:eventStartDatetime',
    // ... rest ...
]);
```

**Replace With**:
```php
$validated = $this->validate([
    'eventTitle' => 'required|string|max:255',
    'eventDescription' => 'nullable|string',
    'eventStartDatetime' => 'required|date',
    'eventEndDatetime' => 'nullable|date|after:eventStartDatetime',
    'eventAllDay' => 'boolean',
    'eventLocation' => 'nullable|string|max:255',
    'eventColor' => 'nullable|string|max:7',
    'eventStatus' => 'nullable|string|in:scheduled,cancelled,completed,tentative',
], [], [
    'eventTitle' => 'title',
    'eventDescription' => 'description',
    'eventStartDatetime' => 'start date/time',
    'eventEndDatetime' => 'end date/time',
    'eventAllDay' => 'all day',
    'eventLocation' => 'location',
    'eventColor' => 'color',
    'eventStatus' => 'status',
]);
```

#### SUBTASK 4.2.2: Update createEvent() Event::create() Call - Add Auto-calculation

**Find**:
```php
Event::create([
    'user_id' => auth()->id(),
    'title' => $this->eventTitle,
    'description' => $this->eventDescription ?: null,
    'start_datetime' => $this->eventStartDatetime,
    'end_datetime' => $this->eventEndDatetime,
    // ... rest ...
]);
```

**Replace With**:
```php
// Auto-calculate end_datetime if not provided
$startDatetime = Carbon::parse($this->eventStartDatetime);
$endDatetime = $this->eventEndDatetime
    ? Carbon::parse($this->eventEndDatetime)
    : $startDatetime->copy()->addHour();

Event::create([
    'user_id' => auth()->id(),
    'title' => $this->eventTitle,
    'description' => $this->eventDescription ?: null,
    'start_datetime' => $startDatetime,
    'end_datetime' => $endDatetime,
    'all_day' => $this->eventAllDay,
    'timezone' => auth()->user()->timezone ?? config('app.timezone'),
    'location' => $this->eventLocation ?: null,
    'color' => $this->eventColor,
    'status' => $this->eventStatus ?: 'scheduled',
]);
```

**Required Import** (add at top if not present):
```php
use Carbon\Carbon;
```

#### SUBTASK 4.2.3: Update Blade Template - Remove Required from end_datetime

**Find**:
```blade
<flux:input wire:model="eventEndDatetime" label="End Date & Time" type="datetime-local" required />
```

**Replace With**:
```blade
<flux:input wire:model="eventEndDatetime" label="End Date & Time (optional)" type="datetime-local" />
```

---

### TASK 4.3: Update show-task-detail.blade.php

**File**: `resources/views/livewire/workspace/show-task-detail.blade.php`

#### SUBTASK 4.3.1: Update loadTaskData() Method

**Find**:
```php
$this->status = $this->task->status->value;
```

**Replace With**:
```php
$this->status = $this->task->status?->value ?? 'to_do';
```

#### SUBTASK 4.3.2: Update save() Validation

**Find**:
```php
$validated = $this->validate([
    'title' => 'required|string|max:255',
    'description' => 'nullable|string',
    'status' => 'required|string|in:to_do,doing,done',
    'priority' => 'nullable|string|in:low,medium,high,urgent',
    'complexity' => 'nullable|string|in:simple,moderate,complex',
    'duration' => 'nullable|integer|min:1',
    'startDate' => 'required|date',
    'startTime' => 'nullable|date_format:H:i',
    'endDate' => 'required|date|after_or_equal:startDate',
    'projectId' => 'nullable|exists:projects,id',
]);
```

**Replace With**:
```php
$validated = $this->validate([
    'title' => 'required|string|max:255',
    'description' => 'nullable|string',
    'status' => 'nullable|string|in:to_do,doing,done',
    'priority' => 'nullable|string|in:low,medium,high,urgent',
    'complexity' => 'nullable|string|in:simple,moderate,complex',
    'duration' => 'nullable|integer|min:1',
    'startDate' => 'nullable|date',
    'startTime' => 'nullable|date_format:H:i',
    'endDate' => 'nullable|date|after_or_equal:startDate',
    'projectId' => 'nullable|exists:projects,id',
]);
```

#### SUBTASK 4.3.3: Update save() Task Update Call

**Find**:
```php
$this->task->update([
    'title' => $this->title,
    'description' => $this->description ?: null,
    'status' => $this->status,
    'priority' => $this->priority ?: null,
    'complexity' => $this->complexity ?: null,
    'duration' => $this->duration ?: null,
    'start_date' => $this->startDate,
    'start_time' => $startTime,
    'end_date' => $this->endDate,
    'project_id' => $this->projectId ?: null,
]);
```

**Replace With**:
```php
$this->task->update([
    'title' => $this->title,
    'description' => $this->description ?: null,
    'status' => $this->status ?: null,
    'priority' => $this->priority ?: null,
    'complexity' => $this->complexity ?: null,
    'duration' => $this->duration ?: null,
    'start_date' => $this->startDate ?: null,
    'start_time' => $startTime,
    'end_date' => $this->endDate ?: null,
    'project_id' => $this->projectId ?: null,
]);
```

#### SUBTASK 4.3.4: Update Blade Template - Remove Required Attributes

**Find**:
```blade
<flux:select wire:model="status" label="Status" required>
```

**Replace With**:
```blade
<flux:select wire:model="status" label="Status">
    <option value="">Select Status</option>
```

**Find**:
```blade
<flux:input wire:model="startDate" label="Start Date" type="date" required />
<flux:input wire:model="endDate" label="Due Date" type="date" required />
```

**Replace With**:
```blade
<flux:input wire:model="startDate" label="Start Date (optional)" type="date" />
<flux:input wire:model="endDate" label="Due Date (optional)" type="date" />
```

---

### TASK 4.4: Update show-event-detail.blade.php

**File**: `resources/views/livewire/workspace/show-event-detail.blade.php`

#### SUBTASK 4.4.1: Update loadEventData() Method

**Find**:
```php
$this->endDatetime = $this->event->end_datetime->format('Y-m-d\TH:i');
```

**Replace With**:
```php
$this->endDatetime = $this->event->end_datetime?->format('Y-m-d\TH:i') ?? '';
```

**Also Update**:
```php
$this->status = $this->event->status?->value ?? 'scheduled';
```

#### SUBTASK 4.4.2: Update save() Validation

**Find**:
```php
$validated = $this->validate([
    'title' => 'required|string|max:255',
    'description' => 'nullable|string',
    'startDatetime' => 'required|date',
    'endDatetime' => 'required|date|after:startDatetime',
    // ... rest ...
]);
```

**Replace With**:
```php
$validated = $this->validate([
    'title' => 'required|string|max:255',
    'description' => 'nullable|string',
    'startDatetime' => 'required|date',
    'endDatetime' => 'nullable|date|after:startDatetime',
    'allDay' => 'boolean',
    'location' => 'nullable|string|max:255',
    'color' => 'nullable|string|max:7',
    'status' => 'nullable|string|in:scheduled,cancelled,completed,tentative',
]);
```

#### SUBTASK 4.4.3: Update save() Event Update Call - Add Auto-calculation

**Find**:
```php
$this->event->update([
    'title' => $this->title,
    'description' => $this->description ?: null,
    'start_datetime' => $this->startDatetime,
    'end_datetime' => $this->endDatetime,
    // ... rest ...
]);
```

**Replace With**:
```php
// Auto-calculate end_datetime if not provided
$startDatetime = Carbon::parse($this->startDatetime);
$endDatetime = $this->endDatetime
    ? Carbon::parse($this->endDatetime)
    : $startDatetime->copy()->addHour();

$this->event->update([
    'title' => $this->title,
    'description' => $this->description ?: null,
    'start_datetime' => $startDatetime,
    'end_datetime' => $endDatetime,
    'all_day' => $this->allDay,
    'location' => $this->location ?: null,
    'color' => $this->color,
    'status' => $this->status ?: 'scheduled',
]);
```

**Required Import** (add at top if not present):
```php
use Carbon\Carbon;
```

#### SUBTASK 4.4.4: Update Blade Template - Remove Required from end_datetime

**Find**:
```blade
<flux:input wire:model="endDatetime" label="End Date & Time" type="datetime-local" required />
```

**Replace With**:
```blade
<flux:input wire:model="endDatetime" label="End Date & Time (optional)" type="datetime-local" />
```

---

### TASK 4.5: Update index.blade.php - Main Workspace Component

**File**: `resources/views/livewire/workspace/index.blade.php`

#### SUBTASK 4.5.1: Update filteredItems() Method - Handle Null Dates

**Find** (in filteredItems method, task filtering section):
```php
if ($item->item_type === 'task') {
    if (! $item->start_date) {
        return false;
    }
    // ... rest of filtering ...
}
```

**Replace With**:
```php
if ($item->item_type === 'task') {
    // If task has no dates, don't show in date-filtered view
    if (! $item->start_date && ! $item->end_date) {
        return false;
    }

    $startDate = $item->start_date instanceof Carbon
        ? $item->start_date->format('Y-m-d')
        : Carbon::parse($item->start_date)->format('Y-m-d');

    $endDate = null;
    if ($item->end_date) {
        $endDate = $item->end_date instanceof Carbon
            ? $item->end_date->format('Y-m-d')
            : Carbon::parse($item->end_date)->format('Y-m-d');
    }

    // Include task if it starts, ends, or spans the target date
    return ($startDate === $targetDate) ||
           ($endDate === $targetDate) ||
           ($endDate && $startDate <= $targetDate && $endDate >= $targetDate);
}
```

**Apply similar logic for events and projects** - ensure they handle null dates gracefully.

#### SUBTASK 4.5.2: Update updateItemDateTime() Method - Handle Null Dates

**Find** (in updateItemDateTime method, task section):
```php
if ($itemType === 'task') {
    $startDateTime = Carbon::parse($newStart);
    $model->start_date = $startDateTime->toDateString();
    $model->start_time = $startDateTime->format('H:i:s');

    if ($newEnd) {
        $model->end_date = Carbon::parse($newEnd)->toDateString();
        // Calculate duration from start and end times
        $endDateTime = Carbon::parse($newEnd);
        $model->duration = $startDateTime->diffInMinutes($endDateTime);
    }
}
```

**Replace With**:
```php
if ($itemType === 'task') {
    if ($newStart) {
        $startDateTime = Carbon::parse($newStart);
        $model->start_date = $startDateTime->toDateString();
        $model->start_time = $startDateTime->format('H:i:s');
    } else {
        $model->start_date = null;
        $model->start_time = null;
    }

    if ($newEnd) {
        $model->end_date = Carbon::parse($newEnd)->toDateString();
        // Calculate duration from start and end times
        if ($model->start_date && $model->start_time) {
            $startDateTime = Carbon::parse($model->start_date . ' ' . $model->start_time);
            $endDateTime = Carbon::parse($newEnd);
            $model->duration = $startDateTime->diffInMinutes($endDateTime);
        }
    } else {
        $model->end_date = null;
    }
}
```

**Find** (in updateItemDateTime method, event section):
```php
elseif ($itemType === 'event') {
    $model->start_datetime = Carbon::parse($newStart);
    if ($newEnd) {
        $model->end_datetime = Carbon::parse($newEnd);
    }
}
```

**Replace With**:
```php
elseif ($itemType === 'event') {
    if ($newStart) {
        $model->start_datetime = Carbon::parse($newStart);
    }
    if ($newEnd) {
        $model->end_datetime = Carbon::parse($newEnd);
    } elseif ($newStart) {
        // Auto-calculate if start provided but no end
        $model->end_datetime = Carbon::parse($newStart)->addHour();
    }
}
```

---

## PHASE 5: Display Components

### TASK 5.1: Update task-card.blade.php

**File**: `resources/views/components/workspace/task-card.blade.php`

**Action**: Add null checks for date display

**Find** (where dates are displayed):
```blade
{{ $task->start_date->format('M j') }}
{{ $task->end_date->format('M j') }}
```

**Replace With**:
```blade
@if($task->start_date)
    <span>{{ $task->start_date->format('M j') }}</span>
@else
    <span class="text-zinc-400">No date</span>
@endif

@if($task->end_date)
    <span>{{ $task->end_date->format('M j') }}</span>
@else
    <span class="text-zinc-400">No due date</span>
@endif
```

---

### TASK 5.2: Update event-card.blade.php

**File**: `resources/views/components/workspace/event-card.blade.php`

**Action**: Add null check for end_datetime display

**Find** (where end_datetime is displayed):
```blade
{{ $event->end_datetime->format('M j, g:i A') }}
```

**Replace With**:
```blade
@if($event->end_datetime)
    <span>{{ $event->end_datetime->format('M j, g:i A') }}</span>
@else
    <span class="text-zinc-400">No end time</span>
@endif
```

---

### TASK 5.3: Update project-card.blade.php

**File**: `resources/views/components/workspace/project-card.blade.php`

**Action**: Add null checks for date display

**Find** (where dates are displayed):
```blade
{{ $project->start_date->format('M j') }} - {{ $project->end_date->format('M j') }}
```

**Replace With**:
```blade
@if($project->start_date && $project->end_date)
    <span>{{ $project->start_date->format('M j') }} - {{ $project->end_date->format('M j') }}</span>
@elseif($project->start_date)
    <span>Starts {{ $project->start_date->format('M j') }}</span>
@else
    <span class="text-zinc-400">No dates set</span>
@endif
```

---

### TASK 5.4: Update calendar-view.blade.php

**File**: `resources/views/livewire/workspace/calendar-view.blade.php`

**Action**: Ensure filtering logic handles nullable dates

**Check**: The `events()` computed property should filter out events without `start_datetime` or handle them appropriately.

**Find** (in events() method):
```php
->where(function ($query) use ($start, $end) {
    $query->whereBetween('start_datetime', [$start, $end])
        // ... rest ...
});
```

**Ensure**: Events without `start_datetime` are excluded from calendar view (or handled in a separate section).

---

## PHASE 6: Testing

### TASK 6.1: Update Existing Tests

**Files**: All test files in `tests/Feature/` that create tasks/events

**Action**: Update test factories and creation calls to reflect optional fields

**Find** (in tests):
```php
Task::create([
    'user_id' => $user->id,
    'title' => 'Test Task',
    'status' => 'to_do',
    'priority' => 'medium',
    'complexity' => 'simple',
    'start_date' => now(),
    'end_date' => now()->addDay(),
]);
```

**Replace With**:
```php
Task::create([
    'user_id' => $user->id,
    'title' => 'Test Task',
    // All other fields optional - defaults will be applied
]);
```

---

### TASK 6.2: Add New Tests

**File**: `tests/Feature/TasksTest.php` (or create new test file)

**Action**: Add test for minimal task creation

**Add**:
```php
test('user can create task with only title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $task = Task::create([
        'user_id' => $user->id,
        'title' => 'Quick Task',
    ]);

    expect($task->status)->toBe(TaskStatus::ToDo);
    expect($task->priority)->toBe(TaskPriority::Medium);
    expect($task->complexity)->toBe(TaskComplexity::Moderate);
    expect($task->start_date)->toBeNull();
    expect($task->end_date)->toBeNull();
});
```

**File**: `tests/Feature/EventsTest.php` (or create new test file)

**Action**: Add test for event end_datetime auto-calculation

**Add**:
```php
test('event end_datetime is auto-calculated if not provided', function () {
    $user = User::factory()->create();
    $start = now();

    $event = Event::create([
        'user_id' => $user->id,
        'title' => 'Quick Event',
        'start_datetime' => $start,
    ]);

    expect($event->end_datetime->format('Y-m-d H:i'))
        ->toBe($start->copy()->addHour()->format('Y-m-d H:i'));
});
```

---

## Verification Checklist

After completing all phases, verify:

- [ ] Can create task with only title
- [ ] Task defaults are applied (status=to_do, priority=medium, complexity=moderate)
- [ ] Can create event with only title and start_datetime
- [ ] Event end_datetime is auto-calculated (start + 1 hour)
- [ ] Event timezone is auto-set from user
- [ ] All form validations accept nullable fields
- [ ] Display components show "Not set" for null dates
- [ ] Filtering logic handles null dates correctly
- [ ] All tests pass
- [ ] No breaking changes to existing functionality

---

## Notes

- **Backward Compatibility**: All changes maintain backward compatibility. Existing tasks/events will continue to work.
- **Database**: Run migrations in order: Task migration first, then Event migration.
- **Models**: Defaults are applied in model events, so they work automatically.
- **Validation**: Server-side validation remains strict, but accepts nullable fields.
- **UI**: Forms should visually indicate optional fields (e.g., "(optional)" in labels).

---

## File Reference Summary

**Migrations**:
- `database/migrations/[timestamp]_make_task_fields_optional_with_defaults.php`
- `database/migrations/[timestamp]_make_event_fields_optional_with_defaults.php`

**Models**:
- `app/Models/Task.php`
- `app/Models/Event.php`

**Form Requests**:
- `app/Http/Requests/TaskStoreRequest.php`
- `app/Http/Requests/EventStoreRequest.php`

**Livewire Volt Components**:
- `resources/views/livewire/workspace/create-item-modal.blade.php`
- `resources/views/livewire/workspace/show-task-detail.blade.php`
- `resources/views/livewire/workspace/show-event-detail.blade.php`
- `resources/views/livewire/workspace/index.blade.php`

**Display Components**:
- `resources/views/components/workspace/task-card.blade.php`
- `resources/views/components/workspace/event-card.blade.php`
- `resources/views/components/workspace/project-card.blade.php`
- `resources/views/livewire/workspace/calendar-view.blade.php`

**Tests**:
- `tests/Feature/TasksTest.php`
- `tests/Feature/EventsTest.php`
- All other test files that create tasks/events
