### Overview

This one-month roadmap translates the `Docs/PRD.md` for **TASKLYST** into a realistic MVP implementation plan. The focus is a vertical slice across core modules: Tasks, Projects, Scheduling, Notifications, Dashboard, and initial LLM Assistant touchpoints (Smart Prioritize and AI Chat dock), built with Laravel 12, Livewire/Volt, Flux UI, Tailwind, Reverb, and Prism.

---

### Scope

#### In-Scope (Month 1 MVP)

- **Tasks Module**
  - Core models and persistence for `Task` (single, non-recurring).
  - Basic fields: title, description, status, due date, priority, tags (simple string or basic tag model), user ownership.
  - Soft deletes and basic policies for user scoping.
  - Simple task list and board (column-based by status) using Livewire + Flux UI with:
    - Create/edit/delete.
    - Inline status changes and basic drag-and-drop between columns.
- **Projects Module**
  - `Project` model with relationships to `Task`.
  - Basic fields: name, description, status, start/end dates.
  - Simple project detail view with associated tasks.
- **Scheduling (Calendar / Events)**
  - Single `Event` model for non-recurring events.
  - Link `Task` to zero/one `Event` (e.g., scheduled work block).
  - Basic weekly time-grid view for events + linked tasks (read-only except drag-to-move for events).
- **Notifications & Reminders**
  - `Reminder` model with:
    - Type (e.g., task_due), scheduled_at, delivery_channel (start with in-app only).
  - In-app notifications list with mark-as-read and basic filtering.
  - Simple user preference stub (e.g., enable/disable reminders, default reminder offset).
- **Dashboard Module**
  - Dashboard page with a small set of widgets:
    - Today’s tasks / overdue tasks.
    - Upcoming events (next 7 days).
    - Simple completion trend (e.g., tasks completed in last 7 days).
  - Widgets backed by aggregation queries (no heavy analytics pipeline yet).
- **LLM Assistant Module**
  - Prism + Ollama integration wired to a single provider/model (e.g., `hermes3:3b`) behind a service.
  - Smart Prioritize flow for a single task:
    - Endpoint/Livewire action to send minimal context (task + project snapshot + upcoming workload summary).
    - `Prism::structured()` call using a simple priority suggestion schema.
    - Apply suggestion to `Task.priority` when user accepts.
  - AI Chat Assistant dock:
    - Simple chat UI with history in current session (no long-term storage initially).
    - Backend endpoint using Prism text generation for Q&A about current tasks/projects.
- **Foundational**
  - Authentication and basic user profile.
  - Core layout, navigation (Dashboard, Workspace, Calendar, Notifications, Profile).
  - Initial test coverage for key flows (task CRUD, Smart Prioritize happy-path).

#### Explicitly Out-of-Scope (Month 1)

- Full recurring models (`RecurringTask`, `TaskInstance`, `TaskException`, recurring events).
- Complex notification channels (email, SMS, push) beyond in-app.
- Advanced analytics snapshots and export hooks (ICS, CSV).
- Full Smart Scheduling (multi-block proposals, conflict reconciliation and holds).
- Advanced assistant tooling such as `PlanNotificationCadence` and deep schema validation.
- Multi-tenant or collaborative features (assume single-user ownership model).
- Full audit history for assistant interactions and device logins.

---

### Weekly Plan

#### Week 1 – Foundations & Core Domain (Tasks + Projects)

- **Backend**
  - Define core models and migrations:
    - `User` (leverage Laravel auth).
    - `Task` with core fields and soft deletes.
    - `Project` with relationship to `Task`.
    - Optional `Tag`/pivot if tags are separate entities; otherwise start with simple string tags on `Task`.
  - Implement Eloquent relationships and basic scopes (e.g., per-user ownership, active vs completed).
  - Add policies for `Task` and `Project` to enforce user scoping.
- **Frontend**
  - Set up base layout, navigation shell, and Flux UI integration.
  - Implement Workspace list view:
    - List tasks for current user with simple filters (status, project).
    - Create/edit forms using Livewire/Volt + Flux UI fields.
  - Implement basic Kanban-style board:
    - Columns by status (e.g., Backlog, In Progress, Done).
    - Drag-and-drop to update status (optimistic UI).
  - Simple Project index + detail page:
    - Create/edit project.
    - Show tasks belonging to a project.
- **Quality**
  - Seed a few demo tasks/projects for manual testing.
  - Introduce first feature tests for task CRUD and policy enforcement.

#### Week 2 – Scheduling & Reminders

- **Backend**
  - Implement `Event` model and migrations:
    - Fields: title, start_at, end_at, timezone, optional `task_id`, user ownership.
  - Service method(s) to generate a simple “upcoming availability” view (no complex recurrence).
  - Implement `Reminder` model:
    - Fields: related entity (initially `Task`), scheduled_at, status, channel (in-app).
  - Basic reminder creation logic on task due date (e.g., due date minus default offset).
- **Frontend**
  - Weekly calendar view (Workspace or dedicated Calendar page):
    - Render `Event` instances in a time grid.
    - Allow drag-to-move an event, updating `start_at`/`end_at`.
    - Visually differentiate events that are linked to tasks.
  - Minimal reminder UI:
    - Reminder chip/indicator on task cards when a reminder exists.
    - Simple inline control to add/edit reminder time relative to due date.
  - Notifications center page:
    - Show in-app notifications/reminders that are due or overdue.
    - Allow mark-as-read and basic filtering (e.g., by type/timeframe).
- **Quality**
  - Tests around event ownership and basic time validation.
  - Tests to ensure reminders are created with correct offsets.

#### Week 3 – Dashboard & Assistant (Smart Prioritize + Chat)

- **Backend**
  - Implement lightweight dashboard aggregation service:
    - Count of overdue + today’s tasks.
    - Upcoming events (next 7 days).
    - Simple rolling completion count (tasks completed in last 7 days).
  - Introduce an Assistant orchestration service:
    - Wrapper around Prism/Ollama configuration (provider, model, defaults).
    - Helper methods for:
      - `suggestTaskPriority(Task $task)`.
      - `chat(array $messages, ?User $userContext = null)`.
  - Define a small structured schema for Smart Prioritize (e.g., suggested priority + short rationale).
- **Frontend**
  - Dashboard page:
    - Cards for key metrics: today/overdue tasks, upcoming events, recent completions.
    - Surface a small “Assistant Insight” area (even if static at first).
  - Smart Prioritize UI:
    - Button on each task card.
    - Modal showing task summary and streaming/loaded assistant recommendation.
    - Controls to accept or cancel; on accept, update `Task.priority` and close.
  - AI Chat dock:
    - Slide-over or bottom dock with simple chat UI.
    - Maintain conversation in session/local state; send to backend for Prism calls.
    - Show typing/loading state, basic error handling.
- **Quality**
  - Feature test for Smart Prioritize happy path:
    - Given a task, calling prioritize updates priority after mock Prism response.
  - Basic test(s) for chat endpoint (e.g., validates input and returns content).

#### Week 4 – Polish, Hardening, and Initial Analytics Hooks

- **UX and Reliability**
  - Refine Flux UI usage for consistency across forms, lists, and modals.
  - Add proper validation and error messaging in Livewire/Volt forms.
  - Improve loading states (`wire:loading`, disabled buttons) for assistant flows and drag/drop.
  - Ensure timezones are applied consistently to events, reminders, and dashboard queries.
- **Security & Permissions**
  - Double-check policies for all task/project/event/reminder interactions.
  - Lock down assistant endpoints to authenticated users and per-user data scope.
- **Analytics & Observability**
  - Add basic event logging for:
    - Task creation/completion.
    - Smart Prioritize calls (invoked, accepted).
    - Chat messages sent/received.
  - Add a simple “completion trend” widget wired to these events or queries.
- **Testing & Cleanup**
  - Expand test coverage for:
    - Workspace CRUD flows.
    - Calendar move operations.
    - Assistant endpoints and error handling.
  - Address high-priority bugs and UX issues discovered during usage.

---

### Dependencies

- **Foundational**
  - Laravel 12 app + authentication scaffold must be in place before Workspace/Dashboard.
  - Database schema for `Task`, `Project`, `Event`, and `Reminder` must exist before wiring UI and assistant flows.
- **Assistant**
  - Prism + Ollama configuration (API/model) must be working before Smart Prioritize and Chat go live.
  - Assistant orchestration service depends on stable task/project/event models for context.
- **Real-Time (Optional for Month 1)**
  - Reverb/WebSockets are optional in Month 1; polling can be used initially with an option to layer real-time updates later.

---

### Risks & Assumptions

- **Risks**
  - LLM latency or instability could degrade Smart Prioritize and Chat UX.
  - Scheduling complexity may grow quickly; keeping Month 1 to non-recurring events is critical.
  - Over-extending dashboard/analytics scope could eat into core feature time.
  - Drag-and-drop interactions and calendar UI can be time consuming to polish.
- **Assumptions**
  - Single-user ownership model (no shared workspaces) for Month 1.
  - In-app notifications are sufficient; other channels can come later.
  - Basic, not exhaustive, analytics are acceptable (simple counts and trends).
  - Assistant behavior can start with conservative prompts and evolve later.

---

### Stretch Goals (If Time Allows)

- Add simple recurring support for tasks or events (e.g., daily/weekly patterns).
- Introduce one external notification channel (e.g., email) for due reminders.
- Enhance dashboard with assistant-generated summaries of past week activity.
- Add basic export (e.g., CSV of tasks or simple ICS feed of events).
