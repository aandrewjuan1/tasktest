# Database Schema Diagram

## System Overview
This is a comprehensive task management and calendar system with project organization, recurring patterns, pomodoro tracking, and notification features.

---

## Core Entities

### 👤 Users
```
users
├── id (PK)
├── name
├── email (unique)
├── email_verified_at
├── workos_id (unique)
├── remember_token
├── avatar
├── created_at
└── updated_at
```
**Relationships:**
- `hasMany` → projects
- `hasMany` → tasks
- `hasMany` → events
- `hasMany` → pomodoro_sessions
- `hasMany` → reminders
- `hasMany` → notifications
- `hasOne` → pomodoro_settings
- `hasOne` → notification_preferences

---

## Project Management

### 📁 Projects
```
projects
├── id (PK)
├── user_id (FK → users)
├── name
├── description
├── start_date
├── end_date
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user
- `hasMany` → tasks
- `morphToMany` → tags (via taggables)

---

## Task Management

### ✅ Tasks
```
tasks
├── id (PK)
├── user_id (FK → users)
├── project_id (FK → projects, nullable)
├── event_id (FK → events, nullable)
├── title
├── description
├── status (enum: to_do, doing, done)
├── priority (enum: low, medium, high, urgent)
├── complexity (enum: simple, moderate, complex)
├── duration (minutes)
├── start_date
├── end_date
├── completed_at
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user
- `belongsTo` → project
- `belongsTo` → event
- `morphToMany` → tags (via taggables)
- `hasOne` → recurring_task
- `hasMany` → pomodoro_sessions
- `morphMany` → reminders

### 🔄 Recurring Tasks
```
recurring_tasks
├── id (PK)
├── task_id (FK → tasks, unique)
├── recurrence_type (enum: daily, weekly, monthly)
├── interval
├── start_date
├── end_date
├── days_of_week
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → task
- `hasMany` → task_instances
- `hasMany` → task_exceptions

### 📅 Task Instances
```
task_instances
├── id (PK)
├── recurring_task_id (FK → recurring_tasks)
├── task_id (FK → tasks, nullable)
├── instance_date
├── status (enum: to_do, doing, done)
├── overridden_title
├── overridden_description
├── completed_at
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → recurring_task
- `belongsTo` → task
- `hasOne` → task_exception (as replacement)

### ⚠️ Task Exceptions
```
task_exceptions
├── id (PK)
├── recurring_task_id (FK → recurring_tasks)
├── exception_date
├── is_deleted (boolean)
├── replacement_instance_id (FK → task_instances, nullable)
├── reason
├── created_by (FK → users, nullable)
├── created_at
├── updated_at
└── UNIQUE(recurring_task_id, exception_date)
```
**Relationships:**
- `belongsTo` → recurring_task
- `belongsTo` → replacement_instance
- `belongsTo` → created_by (user)

---

## Event/Calendar Management

### 📆 Events
```
events
├── id (PK)
├── user_id (FK → users)
├── recurring_event_id (FK → recurring_events, nullable)
├── title
├── description
├── start_datetime (timestampTz)
├── end_datetime (timestampTz)
├── all_day (boolean)
├── timezone
├── location
├── color
├── status (enum: scheduled, cancelled, completed, tentative)
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user
- `belongsTo` → recurring_event
- `hasMany` → tasks
- `morphToMany` → tags (via taggables)
- `morphMany` → reminders

### 🔄 Recurring Events
```
recurring_events
├── id (PK)
├── event_id (FK → events, unique)
├── recurrence_type (enum: daily, weekly, monthly, yearly, custom)
├── interval
├── days_of_week
├── day_of_month
├── nth_weekday
├── rrule
├── start_date
├── end_date
├── occurrence_count
├── timezone
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → event
- `hasMany` → event_instances
- `hasMany` → event_exceptions

### 📅 Event Instances
```
event_instances
├── id (PK)
├── recurring_event_id (FK → recurring_events)
├── event_id (FK → events, nullable)
├── instance_start (timestampTz)
├── instance_end (timestampTz)
├── status (enum: scheduled, cancelled, completed, tentative)
├── overridden_title
├── overridden_description
├── overridden_location
├── all_day (boolean)
├── timezone
├── cancelled (boolean)
├── completed_at
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → recurring_event
- `belongsTo` → event
- `hasOne` → event_exception (as replacement)

### ⚠️ Event Exceptions
```
event_exceptions
├── id (PK)
├── recurring_event_id (FK → recurring_events)
├── exception_date
├── is_deleted (boolean)
├── replacement_instance_id (FK → event_instances, nullable)
├── reason
├── created_by (FK → users, nullable)
├── created_at
├── updated_at
└── UNIQUE(recurring_event_id, exception_date)
```
**Relationships:**
- `belongsTo` → recurring_event
- `belongsTo` → replacement_instance
- `belongsTo` → created_by (user)

---

## Tagging System

### 🏷️ Tags
```
tags
├── id (PK)
├── name (unique)
├── created_at
└── updated_at
```
**Relationships:**
- `morphedByMany` → tasks (via taggables)
- `morphedByMany` → events (via taggables)
- `morphedByMany` → projects (via taggables)

### 🔗 Taggables Pivot
```
taggables
├── id (PK)
├── tag_id (FK → tags)
├── taggable_id (morph)
├── taggable_type (Task, Event, Project)
├── created_at
└── updated_at
```

---

## Pomodoro System

### 🍅 Pomodoro Sessions
```
pomodoro_sessions
├── id (PK)
├── user_id (FK → users)
├── task_id (FK → tasks)
├── session_date
├── start_time
├── end_time
├── duration_minutes
├── work_cycles
├── break_cycles
├── is_completed (boolean)
├── interruptions
├── notes
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user
- `belongsTo` → task

### ⚙️ Pomodoro Settings
```
pomodoro_settings
├── id (PK)
├── user_id (FK → users, unique)
├── work_duration_minutes (default: 25)
├── break_duration_minutes (default: 5)
├── long_break_duration_minutes (default: 15)
├── cycles_before_long_break (default: 4)
├── sound_enabled (boolean, default: true)
├── notifications_enabled (boolean, default: true)
├── auto_start_next_session (boolean, default: false)
├── auto_start_break (boolean, default: false)
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user

---

## Notification System

### ⏰ Reminders
```
reminders
├── id (PK)
├── user_id (FK → users)
├── remindable_id (polymorphic)
├── remindable_type (polymorphic: Task, Event)
├── reminder_type (enum: task_due, event_start, custom)
├── trigger_time
├── time_before_unit (enum: minutes, hours, days)
├── time_before_value
├── is_recurring (boolean)
├── is_sent (boolean)
├── sent_at
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user
- `morphTo` → remindable (Task or Event)

### 🔔 Notifications
```
notifications
├── id (PK)
├── user_id (FK → users)
├── notifiable_id (polymorphic)
├── notifiable_type (polymorphic)
├── notification_type (enum: reminder, task_due, event_start,
│                            pomodoro_break, pomodoro_cycle_complete,
│                            achievement, system)
├── title
├── message
├── data (json)
├── is_read (boolean)
├── read_at
├── channel (enum: in_app, email, push, sms)
├── channel_sent (boolean)
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user
- `morphTo` → notifiable (Task, Event, Pomodoro, Reminder, etc.)

### ⚙️ Notification Preferences
```
notification_preferences
├── id (PK)
├── user_id (FK → users, unique)
├── reminder_notifications_enabled (boolean, default: true)
├── task_due_notifications_enabled (boolean, default: true)
├── event_start_notifications_enabled (boolean, default: true)
├── pomodoro_notifications_enabled (boolean, default: true)
├── achievement_notifications_enabled (boolean, default: true)
├── system_notifications_enabled (boolean, default: true)
├── in_app_enabled (boolean, default: true)
├── email_enabled (boolean, default: false)
├── push_enabled (boolean, default: false)
├── quiet_hours_enabled (boolean, default: false)
├── quiet_hours_start (time)
├── quiet_hours_end (time)
├── notification_frequency (enum: immediate, hourly, daily, weekly)
├── created_at
└── updated_at
```
**Relationships:**
- `belongsTo` → user

---

## System Tables (Laravel Standard)

### 💾 Sessions
```
sessions
├── id (PK, string)
├── user_id (FK → users, nullable)
├── ip_address
├── user_agent
├── payload
└── last_activity
```

### 💾 Cache
```
cache
├── key (PK)
├── value
└── expiration

cache_locks
├── key (PK)
├── owner
└── expiration
```

### 💾 Jobs
```
jobs
├── id (PK)
├── queue
├── payload
├── attempts
├── reserved_at
├── available_at
└── created_at

job_batches
├── id (PK, string)
├── name
├── total_jobs
├── pending_jobs
├── failed_jobs
├── failed_job_ids
├── options
├── cancelled_at
├── created_at
└── finished_at

failed_jobs
├── id (PK)
├── uuid (unique)
├── connection
├── queue
├── payload
├── exception
└── failed_at
```

---

## Entity Relationship Summary

```
USER (1) ───────────────── (Many) PROJECT
  │                           │
  │                           └─── (Many) TASK
  │
  ├────────────────────── (Many) TASK
  │                          │
  │                          ├─── (Many) TAG (via polymorphic taggables)
  │                          │
  │                          ├─── (1) RECURRING_TASK
  │                          │      │
  │                          │      ├─── (Many) TASK_INSTANCE
  │                          │      │
  │                          │      └─── (Many) TASK_EXCEPTION
  │                          │
  │                          └─── (Many) POMODORO_SESSION
  │
  ├────────────────────── (Many) EVENT
  │                          │
  │                          ├─── (Many) TASK
  │                          │
  │                          ├─── (Many) TAG (via polymorphic taggables)
  │
  ├────────────────────── (Many) PROJECT
  │                          │
  │                          ├─── (Many) TAG (via polymorphic taggables)
  │                          │
  │                          └─── (1) RECURRING_EVENT
  │                                 │
  │                                 ├─── (Many) EVENT_INSTANCE
  │                                 │
  │                                 └─── (Many) EVENT_EXCEPTION
  │
  ├────────────────────── (Many) REMINDER (polymorphic: Task/Event)
  │
  ├────────────────────── (Many) NOTIFICATION (polymorphic)
  │
  ├────────────────────── (1) POMODORO_SETTINGS
  │
  └────────────────────── (1) NOTIFICATION_PREFERENCES
```

---

## Key Patterns & Features

### 🔄 Recurring Pattern Architecture
Both tasks and events support recurring patterns with:
- **Base Entity**: The original task/event definition
- **Recurring Configuration**: Defines the recurrence rules
- **Instances**: Generated occurrences of the recurring pattern
- **Exceptions**: Override or cancel specific instances

### 🏷️ Polymorphic Relationships
- **Reminders**: Can be attached to Tasks or Events
- **Notifications**: Can reference any notifiable entity
- **Tags**: Can be attached to Tasks, Events, or Projects

### 📊 Cascade Deletion Strategy
- User deletion → cascades to all user-owned entities
- Project deletion → nullifies associated tasks (sets project_id to null)
- Event deletion → nullifies associated tasks (sets event_id to null)
- Task/Event deletion → cascades to related recurring patterns, instances, and exceptions
- Tag deletion → removes pivot table entries

### 🎯 Status Tracking
- **Tasks**: `to_do`, `doing`, `done`
- **Events**: `scheduled`, `cancelled`, `completed`, `tentative`
- **Priority Levels**: `low`, `medium`, `high`, `urgent`
- **Complexity Levels**: `simple`, `moderate`, `complex`

### ⏱️ Time Management
- Tasks: Date-based with duration in minutes
- Events: DateTime-based with timezone support
- Pomodoro: Session tracking with work/break cycles
- Reminders: Flexible trigger times with unit-based offsets

---

## Database Statistics

| Category | Tables | Description |
|----------|--------|-------------|
| **Core** | 2 | Users, Sessions |
| **Projects** | 1 | Project organization |
| **Tasks** | 4 | Tasks with recurring patterns |
| **Events** | 4 | Calendar events with recurring patterns |
| **Tags** | 2 | Tags with polymorphic assignments |
| **Pomodoro** | 2 | Time tracking sessions and settings |
| **Notifications** | 3 | Reminders, notifications, preferences |
| **System** | 5 | Cache, jobs, failed jobs |
| **Total** | **24** | Complete database tables |

---

*Generated on: 2025-11-28*
*Laravel Version: 12*
*Database: MySQL/PostgreSQL compatible*
