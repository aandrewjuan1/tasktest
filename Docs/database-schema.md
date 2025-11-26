# Database Schema Documentation

## Overview
This document outlines the database structure for the TaskLyst task management system, including projects, tasks, events, tags, pomodoro sessions, and notifications/reminders.

---

## Tables

### Projects Table

Stores project information and serves as a container for related tasks.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users | Owner of the project |
| `name` | String | Required | Project name |
| `description` | Text | Nullable | Project description |
| `start_date` | Date | Nullable | Project start date |
| `end_date` | Date | Nullable | Project end date |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Project → Many Tasks (1:M)
- One Project → One User (M:1)

---

### Tasks Table

Core table for individual tasks with status tracking and priority management.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users | Task owner |
| `title` | String | Required | Task title |
| `description` | Text | Nullable | Task description |
| `status` | Enum | Required | To Do, Doing, Done |
| `priority` | Enum | Required | Task priority level |
| `complexity` | Enum | Required | Task complexity rating |
| `duration` | Integer/Time | Required | Estimated duration |
| `start_date` | Date | Required | Task start date |
| `end_date` | Date | Required | Task end date |
| `project_id` | Integer | Foreign Key → Projects, Nullable | Associated project |
| `completed_at` | Datetime | Nullable | Completion timestamp |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Task → One Project (optional, M:1)
- One Task → One User (M:1)
- One Task → One Recurring_Task (optional, 1:1)
- One Task → Many Tag_Task (1:M)
- One Task → Many Task_Instances (via Recurring_Task, 1:M)
- One Task → Many Pomodoro_Sessions (1:M)

---

### Tags Table

Reusable tags for categorizing tasks and events.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `name` | String | Required, Unique | Tag name |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Tag → Many Tag_Task (1:M)
- One Tag → Many Tag_Events (1:M)

---

### Tag_Task Table

Junction table linking tags to tasks (many-to-many relationship).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `tag_id` | Integer | Foreign Key → Tags | Reference to tag |
| `task_id` | Integer | Foreign Key → Tasks | Reference to task |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Tag_Task → One Tag (M:1)
- One Tag_Task → One Task (M:1)

---

### Recurring_Tasks Table

Defines recurrence patterns for tasks that repeat on a schedule.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `task_id` | Integer | Foreign Key → Tasks, Unique | Master task for series |
| `recurrence_type` | String/Enum | Required | daily, weekly, monthly |
| `interval` | Integer | Required | Recurrence interval (e.g., every 2 days) |
| `start_date` | Date | Required | Recurrence start date |
| `end_date` | Date | Nullable | Recurrence end date |
| `days_of_week` | String | Nullable | For weekly (e.g., Mon, Wed, Fri) |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Recurring_Task → One Task (1:1)
- One Recurring_Task → Many Task_Instances (1:M)
- One Recurring_Task → Many Task_Exceptions (1:M)

---

### Task_Instances Table

Individual occurrences of recurring tasks with per-instance overrides.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `recurring_task_id` | Integer | Foreign Key → Recurring_Tasks | Parent recurring task |
| `task_id` | Integer | Foreign Key → Tasks, Nullable | Optional copied task details |
| `instance_date` | Date | Required | Date of this occurrence |
| `status` | Enum | Required | To Do, Doing, Done |
| `overridden_title` | String | Nullable | Custom title for this instance |
| `overridden_description` | Text | Nullable | Custom description for this instance |
| `completed_at` | Datetime | Nullable | Completion timestamp |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Task_Instance → One Recurring_Task (M:1)
- One Task_Instance → One Task (optional, M:1)

---

### Task_Exceptions Table

Handles exceptions to recurring task patterns (skipped or rescheduled occurrences).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `recurring_task_id` | Integer | Foreign Key → Recurring_Tasks | Series being excepted |
| `exception_date` | Date | Required | Local date of excepted occurrence |
| `is_deleted` | Boolean | Default: false | True if occurrence is skipped |
| `replacement_instance_id` | Integer | Foreign Key → Task_Instances, Nullable | Replacement instance pointer |
| `reason` | Text | Nullable | Exception explanation |
| `created_by` | Integer | Foreign Key → Users, Nullable | Who created the exception |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Constraints:**
- UNIQUE(`recurring_task_id`, `exception_date`)

**Relationships:**
- One Task_Exception → One Recurring_Task (M:1)
- One Task_Exception → One Task_Instance (optional, 1:0..1) — replacement pointer

---

### Events Table

Calendar events with timezone support and optional recurrence.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users | Event owner |
| `title` | String | Required | Event title |
| `description` | Text | Nullable | Event description |
| `start_datetime` | Datetime with TZ | Required | Event start (with timezone) |
| `end_datetime` | Datetime with TZ | Required | Event end (with timezone) |
| `all_day` | Boolean | Default: false | All-day event flag |
| `timezone` | String | Required | IANA timezone (e.g., Asia/Manila) |
| `location` | String | Nullable | Event location |
| `color` | String | Nullable | Calendar display color |
| `status` | Enum | Nullable | scheduled, cancelled, completed, tentative |
| `recurring_event_id` | Integer | Foreign Key → Recurring_Events, Nullable | Link to recurrence series |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Event → One User (M:1)
- One Event → One Recurring_Event (optional, 1:1)
- One Event → Many Tag_Events (1:M)
- One Event → One Event_Instance (optional, 1:1) — if used as master for instance

---

### Recurring_Events Table

Defines complex recurrence patterns for events using iCalendar standards.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `event_id` | Integer | Foreign Key → Events, Unique | Master event for series |
| `recurrence_type` | String/Enum | Required | daily, weekly, monthly, yearly, custom |
| `interval` | Integer | Required | Recurrence interval |
| `days_of_week` | String | Nullable | Weekly days (e.g., MO,WE,FR) |
| `day_of_month` | Integer | Nullable | For monthly-by-day patterns |
| `nth_weekday` | String | Nullable | For patterns like "3rd Monday" (3MO) |
| `rrule` | Text | Nullable | iCalendar RRULE for complex patterns |
| `start_date` | Date | Required | Recurrence effective start (local date) |
| `end_date` | Date | Nullable | Recurrence end date |
| `occurrence_count` | Integer | Nullable | Maximum occurrences limit |
| `timezone` | String | Required | IANA timezone for expansion |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Recurring_Event → One Event (1:1)
- One Recurring_Event → Many Event_Instances (1:M)
- One Recurring_Event → Many Event_Exceptions (1:M)

---

### Event_Instances Table

Individual occurrences of recurring events with per-instance overrides.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `recurring_event_id` | Integer | Foreign Key → Recurring_Events | Parent recurring event |
| `event_id` | Integer | Foreign Key → Events, Nullable | Optional copied event record |
| `instance_start` | Datetime with TZ | Required | Start for this occurrence |
| `instance_end` | Datetime with TZ | Required | End for this occurrence |
| `status` | Enum | Required | scheduled, cancelled, completed, tentative |
| `overridden_title` | String | Nullable | Custom title for this instance |
| `overridden_description` | Text | Nullable | Custom description for this instance |
| `overridden_location` | String | Nullable | Custom location for this instance |
| `all_day` | Boolean | Nullable | Override all-day flag |
| `timezone` | String | Required | IANA timezone for this instance |
| `cancelled` | Boolean | Default: false | Cancellation flag |
| `completed_at` | Datetime | Nullable | Completion timestamp |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Event_Instance → One Recurring_Event (M:1)
- One Event_Instance → One Event (optional, M:1)
- One Event_Instance → Zero-or-One Event_Exception (1:0..1) — when replacement linked

---

### Event_Exceptions Table

Handles exceptions to recurring event patterns (deleted or replaced occurrences).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `recurring_event_id` | Integer | Foreign Key → Recurring_Events | Series being excepted |
| `exception_date` | Date | Required | Local date being excepted |
| `is_deleted` | Boolean | Default: false | True if occurrence is removed |
| `replacement_instance_id` | Integer | Foreign Key → Event_Instances, Nullable | Replacement instance pointer |
| `reason` | Text | Nullable | Exception explanation |
| `created_by` | Integer | Foreign Key → Users, Nullable | Who created the exception |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Constraints:**
- UNIQUE(`recurring_event_id`, `exception_date`)

**Relationships:**
- One Event_Exception → One Recurring_Event (M:1)
- One Event_Exception → One Event_Instance (optional, 1:0..1) — replacement pointer

---

### Tag_Events Table

Junction table linking tags to events (many-to-many relationship).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `tag_id` | Integer | Foreign Key → Tags | Reference to tag |
| `event_id` | Integer | Foreign Key → Events | Reference to event |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Tag_Events → One Tag (M:1)
- One Tag_Events → One Event (M:1)

---

## Pomodoro Sessions

### Pomodoro_Sessions Table

Tracks individual Pomodoro work sessions with focus time and break intervals.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users | User performing session |
| `task_id` | Integer | Foreign Key → Tasks | Task being worked on |
| `session_date` | Date | Required | Date of session |
| `start_time` | Datetime | Required | Session start timestamp |
| `end_time` | Datetime | Nullable | Session end timestamp |
| `duration_minutes` | Integer | Required | Total work duration in minutes |
| `work_cycles` | Integer | Required | Number of work cycles completed |
| `break_cycles` | Integer | Required | Number of breaks taken |
| `is_completed` | Boolean | Default: false | True if session finished normally |
| `interruptions` | Integer | Default: 0 | Count of interruptions during session |
| `notes` | Text | Nullable | Notes about the session |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Pomodoro_Session → One User (M:1)
- One Pomodoro_Session → One Task (M:1)

---

### Pomodoro_Settings Table

User-specific Pomodoro configuration and preferences.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users, Unique | User settings owner |
| `work_duration_minutes` | Integer | Default: 25 | Length of work cycle |
| `break_duration_minutes` | Integer | Default: 5 | Length of short break |
| `long_break_duration_minutes` | Integer | Default: 15 | Length of long break |
| `cycles_before_long_break` | Integer | Default: 4 | Work cycles before long break |
| `sound_enabled` | Boolean | Default: true | Enable notification sounds |
| `notifications_enabled` | Boolean | Default: true | Enable session notifications |
| `auto_start_next_session` | Boolean | Default: false | Auto-start after break |
| `auto_start_break` | Boolean | Default: false | Auto-start break after work cycle |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Pomodoro_Settings → One User (1:1)

---

## Notifications & Reminders

### Reminders Table

Stores reminder configurations for tasks and events.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users | Reminder owner |
| `remindable_id` | Integer | Polymorphic ID | ID of task or event |
| `remindable_type` | String | Polymorphic Type | Task or Event |
| `reminder_type` | Enum | Required | task_due, event_start, custom |
| `trigger_time` | Datetime | Required | When reminder should trigger |
| `time_before_unit` | Enum | Nullable | minutes, hours, days |
| `time_before_value` | Integer | Nullable | Number of units before event |
| `is_recurring` | Boolean | Default: false | Repeat on event recurrence |
| `is_sent` | Boolean | Default: false | Reminder already triggered |
| `sent_at` | Datetime | Nullable | When reminder was sent |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Reminder → One User (M:1)
- One Reminder → One Task or Event (polymorphic M:1)

---

### Notifications Table

Log of all notifications sent to users (read/unread status).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users | Notification recipient |
| `notifiable_id` | Integer | Polymorphic ID | ID of related entity |
| `notifiable_type` | String | Polymorphic Type | Task, Event, Pomodoro, Reminder, etc. |
| `notification_type` | Enum | Required | reminder, task_due, event_start, pomodoro_break, pomodoro_cycle_complete, achievement, system |
| `title` | String | Required | Notification title |
| `message` | Text | Required | Notification message |
| `data` | JSON | Nullable | Additional data (e.g., action links) |
| `is_read` | Boolean | Default: false | Read status |
| `read_at` | Datetime | Nullable | When notification was read |
| `channel` | Enum | Nullable | in_app, email, push, sms |
| `channel_sent` | Boolean | Default: false | Successfully sent via channel |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Notification → One User (M:1)
- One Notification → One Task, Event, Pomodoro_Session, or other entity (polymorphic M:1)

---

### Notification_Preferences Table

User preferences for notification delivery and frequency.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | Integer | Primary Key | Unique identifier |
| `user_id` | Integer | Foreign Key → Users, Unique | Settings owner |
| `reminder_notifications_enabled` | Boolean | Default: true | Enable reminder notifications |
| `task_due_notifications_enabled` | Boolean | Default: true | Enable task due notifications |
| `event_start_notifications_enabled` | Boolean | Default: true | Enable event start notifications |
| `pomodoro_notifications_enabled` | Boolean | Default: true | Enable Pomodoro notifications |
| `achievement_notifications_enabled` | Boolean | Default: true | Enable achievement notifications |
| `system_notifications_enabled` | Boolean | Default: true | Enable system notifications |
| `in_app_enabled` | Boolean | Default: true | Enable in-app notifications |
| `email_enabled` | Boolean | Default: false | Enable email notifications |
| `push_enabled` | Boolean | Default: false | Enable push notifications |
| `quiet_hours_enabled` | Boolean | Default: false | Enable quiet hours mode |
| `quiet_hours_start` | Time | Nullable | Quiet hours start time (HH:MM) |
| `quiet_hours_end` | Time | Nullable | Quiet hours end time (HH:MM) |
| `notification_frequency` | Enum | Default: immediate | immediate, hourly, daily, weekly |
| `created_at` | Timestamp | Auto | Creation timestamp |
| `updated_at` | Timestamp | Auto | Last update timestamp |

**Relationships:**
- One Notification_Preferences → One User (1:1)

---

## Entity Relationship Summary

### Core Entities
- **Users** (referenced but not defined in this schema)
  - Own Projects, Tasks, Events, Pomodoro Sessions, Reminders, and Notifications

- **Projects**
  - Group related Tasks
  - Owned by Users

- **Tasks**
  - Can belong to Projects (optional)
  - Can recur via Recurring_Tasks
  - Can be tagged via Tag_Task
  - Generate Task_Instances when recurring
  - Have multiple Pomodoro_Sessions
  - Can have Reminders

- **Events**
  - Calendar-based with timezone support
  - Can recur via Recurring_Events
  - Can be tagged via Tag_Events
  - Generate Event_Instances when recurring
  - Can have Reminders

- **Tags**
  - Shared between Tasks and Events
  - Many-to-many relationships

### Pomodoro System
- **Pomodoro_Sessions** - Individual work sessions linked to tasks
- **Pomodoro_Settings** - User-specific timer configurations
- Tracks work cycles, breaks, and interruptions
- Supports statistics for productivity analysis

### Notifications & Reminders System
- **Reminders** - Scheduled notifications for tasks/events with flexible timing
- **Notifications** - Log of all notifications with read/unread tracking
- **Notification_Preferences** - User-defined delivery channels and quiet hours
- Supports multiple notification types and delivery channels (in-app, email, push)
- Polymorphic relationships for flexible entity linking

### Recurrence System

**For Tasks:**
- `Recurring_Tasks` → defines pattern
- `Task_Instances` → individual occurrences
- `Task_Exceptions` → handles skips/reschedules

**For Events:**
- `Recurring_Events` → defines pattern (supports iCalendar RRULE)
- `Event_Instances` → individual occurrences
- `Event_Exceptions` → handles skips/replacements
