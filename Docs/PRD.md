# Product Requirements Document (PRD)

This document outlines the requirements for **TASKLYST**: a web-based task management system with an LLM-powered intelligent assistant for task prioritization and predictive scheduling.

---

## Overview

**TASKLYST** allows users to manage tasks, projects, schedules, notifications, pomodoro sessions, and assistant-driven automations through a cohesive workspace. Livewire + Flux UI provide the interactive experience, while Prism-powered AI services deliver contextual insights such as smart prioritization, scheduling, and conversational support.

---

## Pages

### Dashboard
- Central hub summarizing task load, calendar highlights, project health, pomodoro activity, and notification counts in the user's timezone.
- Surfaces assistant insights, recent AI recommendations, and quick actions (e.g., Smart Prioritize, Smart Schedule).
- Widgets reuse underlying module data (tasks, projects, scheduling, notifications) and refresh via Livewire polling or broadcast events.

### Workspace
- Unified space to manage tasks, events, and projects with Kanban and list views, drag-and-drop status changes, inline edits, and quick filters using Flux UI fields.
- Creation and edit forms expose recurrence, reminders, tags, pomodoro links, and Smart Prioritize/Smart Schedule triggers.
- Provides bulk actions, tag filters, project grouping, and in-context assistant suggestions across item types.
- Weekly view (time grid) that combines tasks, events, and projects into a timezone-aware schedule, supporting drag-to-move/resize interactions, inline reminder/tag management, and Reverb-powered real-time updates.
- Calendar view offering a monthly perspective of tasks, events, and projects, integrating Smart Schedule recommendations and displaying proposed holds directly in the grid.

### Analytics
- Focused on project progress, task completion trends, pomodoro productivity, and notification engagement.
- Pulls from cached analytics snapshots and assistant summaries to highlight bottlenecks or opportunities.
- Offers export hooks (e.g., ICS, CSV) and feeds the assistant context builder with historical data.

### Profile
- Unified settings area covering user profile metadata, notification preferences, pomodoro settings, and assistant privacy controls.
- Allows users to toggle channels, configure quiet hours, define default reminder offsets, and set Smart Assistant behavior.
- Exposes audit history for assistant interactions and device logins when needed.

---

## Modules

### Tasks Module
- Manages lifecycle of tasks, recurring definitions, tags, reminders, pomodoro linkage, and assistant-triggered recommendations.
- Each task card exposes Smart Prioritize and Smart Schedule buttons seeded with full task/project/event context.

#### Backend Implementation
- Models: `Task`, `RecurringTask`, `TaskInstance`, `TaskException` with cascading soft deletes, user policies, and eager-loaded relationships.
- Queued jobs generate upcoming instances, reconcile overrides, and emit analytics events.
- Assistant endpoints normalize payloads, attach history, and persist AI responses for auditing.

#### Frontend Implementation
- Livewire boards and list views with drag-and-drop, inline editing, quick filters, and tag chips.
- Rich task forms leveraging Flux UI fields for recurrence, reminders, tags, linked events, and pomodoro sessions.
- Modal panels stream assistant output and allow users to accept/tweak recommendations.

---

### Projects Module
- Aggregates tasks and related analytics to highlight scope, timelines, and workload distribution per project.
- Assistant insights consider project health, milestones, and tag context before suggesting next actions.

#### Backend Implementation
- `Project` relationships to tasks, tags, analytics snapshots, and cached health metrics refreshed asynchronously.
- Service classes compute milestone forecasts and assistant-ready summaries.
- Policies enforce owner scoping while preserving room for future collaboration.

#### Frontend Implementation
- Project dashboards display status chips, progress charts, workload breakdowns, and milestone trackers using Flux UI components.
- Inline editing of metadata, date ranges, and tags with optimistic updates.
- Embedded assistant callouts suggest focus tasks or schedule adjustments.

---

### Scheduling (Calendar / Events) Module
- Provides base events, recurring definitions, overrides, exceptions, and links back to tasks for full calendar coverage.
- Ensures timezone accuracy and synchronizes with assistant scheduling flows.

#### Backend Implementation
- Models for events, recurring definitions, instances, and exceptions with timezone casting and user policies.
- Services to sync task-event relationships, recalc availability windows, and broadcast updates via Reverb/WebSockets.
- Calendar feeds, ICS exports, and assistant queries for upcoming availability.

#### Frontend Implementation
- Responsive calendar views (monthly/weekly/daily) rendered with Livewire + Flux UI.
- Inline forms for base events, overrides, and reminder/tag management.
- Drag-to-resize/move interactions updating backend schedules in real time.

---

### Notification and Reminders Module
- Handles polymorphic reminders (`Task`, `Event`, `PomodoroSession`, etc.) and omni-channel notifications.
- Leverages user preferences and historical delivery outcomes to tune assistant proposals.

#### Backend Implementation
- `Reminder` and `Notification` models backed by state machines for attempts, retries, and read receipts.
- Preference stores capture quiet hours, channel opt-ins, per-category throttles, and default offsets.
- Queued jobs fan out to email, push, SMS, and in-app channels while logging delivery metrics.

#### Frontend Implementation
- Inbox-style notification center with filtering, bulk actions, mark-as-read, and snooze/reschedule affordances.
- Preference management forms mirroring backend validation.
- Reminder chips on tasks/events with inline editing.

---

### Dashboard Module
- Composes data from tasks, projects, scheduling, notifications, and assistant services into glanceable widgets.
- Supports personalization (widget ordering, timeframe filters) and exports quick insights to other pages.

#### Backend Implementation
- Aggregation services that fetch latest metrics, assistant summaries, and real-time alerts for each widget.
- Broadcasting/polling mechanisms to keep dashboard tiles fresh without full page reloads.

#### Frontend Implementation
- Flux UI cards for task burndown, upcoming events, project momentum, pomodoro streaks, and notification digest.
- Widget layout manager with drag-and-drop ordering and persisted preferences.
- Inline assistant entry points for launching smart actions directly from cards.

---

### LLM Assistant Module
- Orchestrates Prism-powered AI flows using the `hermes3:3b` model via the Ollama driver.
- Consumes unified task, project, event, pomodoro, and notification context to produce actionable insights.
- Maintains tool registry (`UpdateTaskPriority`, `ProposeScheduleBlock`, `PlanNotificationCadence`, etc.) and structured response schemas.

#### Backend Implementation
- Assistant orchestration layer handling prompt assembly, context injection, Prism calls, and response storage.
- Middleware/policies to guard data sharing and retain redacted logs for compliance.
- Streaming/webhook handlers feed UI components, while validators enforce JSON Schema contracts.

#### Frontend Implementation
- Assistant modals for Smart Prioritize and Smart Schedule with token streaming, rationale snippets, and accept/tweak flows.
- Chat-style assistant dock with conversation history, suggested prompts, quick actions, and feedback capture.
- Livewire hooks dispatch success/error states back to modules consuming assistant outputs.

---

### Smart Prioritization Button
- CTA embedded on every task card and dashboard widget to request AI-driven priority adjustments.

#### Experience
- Opens a modal summarizing task metadata, recent completions, project commitments, and assistant rationale.
- Users can accept the recommendation, adjust priority manually, or ask follow-up questions before applying.

#### Backend Implementation
- Endpoint packages task, project, scheduling, and notification context before invoking `Prism::structured()` with the prioritization schema.
- Tool handlers update task priority, log AI reasoning, and emit events to dashboards/analytics.

#### Frontend Implementation
- Flux UI modal with streaming assistant output, diff preview, and confirmation controls.
- Wire states (`wire:loading`, `wire:target`) disable conflicting interactions while recommendations stream in.

---

### Smart Scheduling Button
- Provides AI-generated time blocks for a task by evaluating availability, workload, pomodoro preferences, and notification quiet hours.

#### Experience
- Modal displays suggested schedule windows, conflicts, and ability to push updates directly to the calendar.
- Supports partial acceptance (e.g., choose one suggested block) and captures user feedback for tuning.

#### Backend Implementation
- Assistant request aggregates event calendars, recurring tasks, pomodoro cadence, and notification constraints.
- Tool handlers create temporary holds or confirmed events, reconcile with recurring definitions, and broadcast updates.

#### Frontend Implementation
- Calendar-aligned modal showing proposed blocks alongside current availability.
- One-click actions to accept, reschedule, or ask the assistant for alternatives.

---

### AI Chatbot Assistant
- Embedded conversational dock enabling users to ask open-ended questions, request summaries, negotiate schedules, or trigger automations.

#### Backend Implementation
- Shares orchestration, tooling, and schema validation logic with the LLM Assistant module while persisting conversation transcripts.
- Supports follow-up turns by rehydrating recent messages, user preferences, and relevant entities.

#### Frontend Implementation
- Chat UI with history, suggested prompts, quick action buttons, and thumbs-up/down feedback controls.
- Uses Livewire events to push assistant responses, highlight referenced entities, and trigger page navigations when needed.

---
