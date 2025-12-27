# Livewire Volt Component Guide for TaskLyst

## Overview

This document provides a guide for building the livewire components of the system.

## Technologies
- Livewire + Alpine.js: used for interactivity and dynamic UI updates
- Tailwind CSS: used for styling the UI
- HTML 5: used for drag and drop functionality

---

## Workspace Page Components
This is the page where the user can manage all their tasks, events, projects and calendar.
All components are in the `resources/views/livewire/pages/workspace` directory.

### Index Component
-Main layout of the page has 2 columns wherein 75 percent leftside is where we can see their task, event, projects, then 25 percent rightside is where we can see their calendar.
- **index.blade.php**: The main workspace page layout with 75% left column (tasks/events/projects) and 25% right column (calendar).

#### Unified Multi-View Display
- **show-items.blade.php**: Main Livewire Volt component for displaying tasks, events, and projects. This component includes:
  - **View switching**: Toggle between list, kanban, and weekly timegrid views
  - **Search bar**: Full-text search across task/event/project titles and descriptions
  - **Filter sidebar**: Filter by status, priority, complexity, project, tags, date range (today, this week, this month, custom, overdue)
  - **Tag filter chips**: Display selected tag filters as removable chips/badges
  - **Date range picker**: Custom date range filtering
  - **Sort dropdown**: Sort by due date, priority, complexity, created date, or title (A-Z)
  - **Pagination**: For large item lists (list view only)
  - **Empty state**: Message when no items match filters
  - **Loading skeleton**: Loading placeholders for async data
  - **Kanban columns**: Status-based columns (to_do, doing, done) for kanban view
  - **Drag-and-drop handler**: HTML5 drag-and-drop for moving tasks between kanban columns and rescheduling in weekly view
  - **Weekly timegrid view**: Google Calendar-style week view with time slots, all-day section, and customizable time range
  - **Quick create button**: Floating action button with dropdown menu for quick creation (Create Task, Create Event, Create Project)
  - **Bulk actions bar**: Action bar that appears when items are selected, providing bulk operations (status change, priority change, tag assignment, delete, project assignment)
  - **Item selection**: Checkbox functionality for selecting items for bulk operations (list view only)
  - Uses child card components (task-card, event-card, project-card) for individual item rendering - these may be regular Blade components or Volt components depending on interactivity needs

#### Item Card Components (Optional Volt Components)
- **task-card.blade.php**: Volt component for displaying a single task card with title, description preview, status badge, priority indicator, complexity badge, due date, tags, project link, and quick actions. Only needed if card has interactive Livewire logic (e.g., inline editing, quick status change). Otherwise can be a regular Blade component.
- **event-card.blade.php**: Volt component for displaying a single event card with title, description preview, date/time, location, status badge, color indicator, tags, and quick actions. Only needed if card has interactive Livewire logic.
- **project-card.blade.php**: Volt component for displaying a single project card with name, description preview, date range, task count/progress, tags, and quick actions. Only needed if card has interactive Livewire logic.

#### Detail View Components (Separate per type)
- **show-task-detail.blade.php**: Full detail view for a single task showing all fields, relationships (project, event, tags), reminders, collaboration section, messages/chat, and Pomodoro quick start.
- **show-event-detail.blade.php**: Full detail view for a single event showing all fields, relationships (tasks, tags), reminders, collaboration section, and messages/chat.
- **show-project-detail.blade.php**: Full detail view for a single project showing all fields, associated tasks list, tags, reminders, collaboration section, and messages/chat.

### Form Components

#### Create Component (Unified)
- **create-item-modal.blade.php**: Unified modal for creating tasks, events, or projects. Contains tabs or type selector to switch between Task | Event | Project forms. Shows appropriate form fields based on selection.

#### Edit Components (Separate per type)
- **edit-task-form.blade.php**: Edit form for tasks with all task-specific fields (title, description, status, priority, complexity, duration, dates, project, event, tags).
- **edit-event-form.blade.php**: Edit form for events with all event-specific fields (title, description, start/end datetime, all-day toggle, timezone, location, color, status, tags).
- **edit-project-form.blade.php**: Edit form for projects with all project-specific fields (name, description, start/end dates, tags).

### Calendar Components
- **calendar-view.blade.php**: Livewire Volt component for the right sidebar (25% width). Displays month view only with item counts (events + tasks) per day. Includes simple navigation controls (previous/next month arrows and "Today" button). Clicking a day switches the main workspace to weekly timegrid view showing that day's week.
- **calendar-event-popover.blade.php**: Livewire Volt component showing event details when clicking on calendar events. Handles event data loading and display logic.
- **timegrid-settings-modal.blade.php**: Livewire Volt component for configuring weekly timegrid view preferences (start/end hour, hour height, slot increment, default event duration, show weekends). Settings are persisted per user in the `timegrid_settings` table.

## Component Organization Strategy

### Displaying Multiple Items
- **One unified Livewire Volt component** (`show-items.blade.php`) handles:
  - List, kanban, and weekly timegrid views (view switching)
  - All filtering functionality (status, priority, complexity, project, tags, date range)
  - Search functionality
  - Sorting and pagination
  - Empty states and loading states
  - Kanban drag-and-drop functionality
  - Weekly timegrid with drag-and-drop rescheduling
  - Week navigation (previous/next week, go to today)
  - Current time indicator on weekly view
  - Quick create button with dropdown
  - Bulk actions bar and item selection
- All filtering, searching, view switching, and action logic is contained within this component since they're tightly coupled to item display
- Uses child card components (task-card, event-card, project-card) for individual item rendering - these may be regular Blade components if they don't need Livewire logic
- Same data structure and filtering logic across all views
- Weekly view displays items with specific times in time slots, and items without times in the all-day section

### Showing Single Item (Detail View)
- **Separate components** for each type (task, event, project)
- Different fields, relationships, and actions per type
- Easier to maintain and extend independently

### Editing Items
- **Separate components** for each type (task, event, project)
- Different validation rules and form fields per type
- Cleaner code organization and maintainability

### Creating Items
- **One unified component** (`create-item-modal.blade.php`) with tabs/type selector
- Better UX with single entry point
- Shared modal structure with type-specific forms

## Implementation Priority

### Phase 1 (MVP)
- index.blade.php
- calendar-view.blade.php
- show-items.blade.php (list view with basic search, filters, and quick create button)
- create-item-modal.blade.php

### Phase 2 (Enhanced UX) - COMPLETED
- show-items.blade.php (add kanban view and view switcher) ✓
- show-items.blade.php (add advanced filters: tags, date range, project selector) ✓
- show-items.blade.php (add bulk actions bar and item selection) ✓
- show-items.blade.php (add weekly timegrid view with customizable settings) ✓
- timegrid-settings-modal.blade.php (settings management) ✓
- show-task-detail.blade.php, show-event-detail.blade.php, show-project-detail.blade.php
- edit-task-form.blade.php, edit-event-form.blade.php, edit-project-form.blade.php

### Phase 3 (Advanced Features) - COMPLETED
- show-items.blade.php (add drag-and-drop for kanban) ✓
- show-items.blade.php (add drag-and-drop for weekly timegrid rescheduling) ✓
- calendar-view.blade.php (simplified to month-only view with item counts, clickable days to switch to weekly view) ✓
- calendar-event-popover.blade.php ✓
