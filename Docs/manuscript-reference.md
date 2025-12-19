# TASKLYST: A WEB-BASED STUDENT TASK MANAGEMENT SYSTEM USING A HERMES 3 (3B) LLM ASSISTANT FOR INTELLIGENT PRIORITIZATION AND PREDICTIVE SCHEDULING

## Objectives of the Study

This study aims to design and develop a prototype of a web-based student task management system that incorporates a Hermes 3 (3B) Large Language Model (LLM) assistant to enable intelligent task prioritization and predictive scheduling. The research seeks to demonstrate how modern AI techniques can enhance student productivity through adaptive, context-aware task management.

### Specific Research Objectives

The study pursues the following specific objectives:

1. **Design and implement a web-based task management system for EACC students** that facilitates the organization of academic and personal tasks while leveraging AI-assisted prioritization capabilities through the Hermes 3 (3B) Large Language Model.

2. **Integrate the Hermes 3 (3B) Large Language Model** as a conversational assistant that can generate predictive scheduling recommendations and provide clear explanations for its suggestions.

3. **Evaluate the system's effectiveness and functional quality** in accordance with the ISO/IEC 25010 software quality standard, with particular emphasis on measuring improvements in students' time management practices, task completion rates, and levels of procrastination reduction.

---

## Scope and Limitations

### Scope of the Study

TaskLyst is a web-based student task management system that integrates the Hermes 3 (3B) Large Language Model to support intelligent task prioritization and predictive scheduling. The system is designed to assist students in organizing academic and personal tasks, improving time management practices, and enhancing overall productivity through LLM-assisted recommendations and a structured task workflow.

#### Participant and Institutional Scope

The study focuses exclusively on students enrolled at Emilio Aguinaldo College–Cavite (EACC) during the Academic Year 2025–2026. Faculty members and other non-student users are excluded from the study scope.

#### System Modules and Features

The system comprises the following core modules, which will be implemented and evaluated:

- **User, Profile, and Preferences Module**: Handles account registration and basic personalization, including per-user Pomodoro configuration (`pomodoro_settings`) and notification preferences (`notification_preferences`) that control reminder channels, quiet hours, and notification types.

- **Projects and Task Management Module**: Manages projects (`projects`) and tasks (`tasks`) for organizing academic and personal work, including task status (`to_do`, `doing`, `done`), priority levels (`low`, `medium`, `high`, `urgent`), complexity levels (`simple`, `moderate`, `complex`), estimated duration, and optional links between tasks, projects, and calendar events.

- **Recurring Tasks and Events Module**: Supports definition and management of recurring patterns using `recurring_tasks`, `task_instances`, and `task_exceptions` for tasks, and `recurring_events`, `event_instances`, and `event_exceptions` for calendar events, enabling per-occurrence overrides and cancellations.

- **Calendar and Event Scheduling Module**: Provides calendar-based planning and visualization using the `events` table, including start and end datetimes with timezone support, all-day events, locations, color-coding, and event status values (`scheduled`, `cancelled`, `completed`, `tentative`), along with links between events and associated tasks.

- **Tagging and Organization Module**: Implements flexible categorization via `tags` and the polymorphic `taggables` pivot, allowing students to attach tags to tasks, events, and projects for filtering, search, and workload visualization.

- **Pomodoro Focus and Session Tracking Module**: Implements focus-timer functionality and session logging using `pomodoro_sessions` and user-specific `pomodoro_settings`, capturing work/break cycles, interruptions, and notes to support evidence-based productivity scaffolding.

- **Reminders and Notification Delivery Module**: Manages configurable reminders and notifications using `reminders`, `notifications`, and `notification_preferences`, enabling deadline-aware alerts (e.g., task due dates, event start times, Pomodoro milestones), multi-channel delivery (in-app, email, push, SMS), and user-controlled quiet hours and frequency.

- **LLM Assistant and Recommendation Module**: Integrates the Hermes 3 (3B) model as a conversational assistant that reads from the task, project, event, Pomodoro, and tagging data to provide intelligent prioritization, predictive scheduling recommendations, and explainable reasoning, while treating all outputs as decision-support suggestions.

- **Dashboard and Analytics Module**: Offers a summary view of active tasks, upcoming deadlines, calendar events, reminders, and AI-generated recommendations, together with basic analytics derived from `tasks`, `events`, `pomodoro_sessions`, and `notifications` (such as completion trends, focus-time statistics, and reminder response patterns).

These modules together map directly onto the database entities defined in the system’s schema (e.g., `projects`, `tasks`, `events`, recurring pattern tables, tagging, Pomodoro, reminders, and notifications) to ensure implementation and evaluation remain aligned with the underlying data model.

#### Included and Excluded Features

**Included features** encompass task management, scheduling assistance, LLM-based conversational interaction, reminders, Pomodoro-based productivity support, basic analytics, and elementary collaboration capabilities.

**Excluded from this study** are automated academic tutoring systems, motivational coaching systems, enterprise-grade project management functionality, and large-scale cross-institutional task-sharing platforms.

### Limitations and Constraints

#### LLM-Related Constraints

The Hermes 3 (3B) model may occasionally produce inaccurate, inconsistent, or biased suggestions due to inherent limitations of language models. All LLM outputs will be treated as decision-support recommendations rather than authoritative instructions, requiring users to exercise critical judgment when evaluating suggestions.

#### Participant and Behavioral Factors

Measured outcomes may be influenced by individual differences in student motivation, prior time-management proficiency, and levels of engagement with the system. These variables can potentially affect the validity of performance metrics and behavioral assessments.

#### Technical Constraints

Internet connectivity issues, device heterogeneity across participant systems, and browser compatibility differences may influence system usability and data collection completeness.

#### Evaluation and Temporal Constraints

The study's limited timeframe and sample size may reduce statistical power and restrict the ability to conduct long-term behavioral assessment. Outcome measures will combine system logs, self-reported feedback, and ISO/IEC 25010-based quality assessment, each of which carries inherent measurement limitations.

### Ethical Safeguards and Mitigation Strategies

To address identified limitations and ensure responsible research practices, the following safeguards will be implemented:

- The study will obtain institutional ethics approval and secure informed consent from all participants prior to data collection.

- Usage data will be anonymized according to institutional standards and secured using appropriate encryption methods, with voluntary participation and clear opt-out options available to participants.

- LLM output variability will be mitigated by presenting recommendations as suggestions rather than directives, with logging of user acceptance or rejection of each recommendation.

- Technical limitations will be addressed through comprehensive cross-browser testing and specification of minimum device requirements.

- Measurement limitations will be addressed through triangulation of data collected from system logs, user surveys, and ISO/IEC 25010-based evaluations.

---

## Synthesis of Related Studies

### Time Management, Procrastination, and Academic Success

Recent literature consistently demonstrates that poor time management, procrastination behaviors, and executive function deficits represent major obstacles to university student success, contributing to elevated stress levels and diminished academic attainment. Research by Nasrullah and Khan (2021) and Luceño-Moreno and colleagues (2025) provides empirical evidence of these relationships. Meta-analyses and longitudinal studies indicate that scaffolded interventions, which include structured goal setting, prioritized scheduling, and consistent progress monitoring, yield measurable improvements in task completion rates, student sense of control, and overall GPA, while simultaneously reducing procrastination and stress levels. However, generic to-do lists and basic calendar tools do not provide the domain-specific functionality required for effective student task management. Instead, tools designed specifically for student populations should provide deadline-aware planning capabilities, visual workload representation, and time-management scaffolds that reduce cognitive load and decision fatigue.

### Digital Productivity Tools and Adaptive Features

Complementary research on digital productivity tools and time-management techniques demonstrates that well-designed applications, including Pomodoro timers, focus sessions, and distraction-blocking features, increase periods of focused work and reduce task-switching behaviors. Analytical dashboards can guide students toward personalized productivity improvements. Multiple studies by Cranefield and colleagues (2022), Pedersen and associates (2024), and Zhang and colleagues (2021) support these findings. However, these tools frequently fail to adapt as students' schedules and priorities evolve over the semester. Significant empirical gaps remain in systems that seamlessly unify calendar synchronization, adaptive scheduling, and collaboration features tailored to student workflows. Research by Oloyede and Ogunwale (2022) and Gamis (2024) identify these gaps. These findings suggest the necessity of combining proven time-management scaffolds with adaptive, context-aware features rather than relying exclusively on static planning tools.

### AI-Enabled Scheduling and Intelligent Assistants

Emerging research on AI-enabled productivity tools indicates that intelligent assistants can personalize scheduling recommendations, automate repetitive planning tasks, and provide timely reminders that reduce late submissions and associated administrative burden. Controlled and observational studies documented by Klimova and Pikhart (2025) and Rienties and colleagues (2025) report improved on-time submission rates and measurable productivity gains. However, these studies also identify risks including technostress, over-reliance on recommendations, algorithmic bias, and privacy concerns. Consequently, system design must treat AI outputs as explainable decision-support mechanisms with user control mechanisms and explicit evaluation of student well-being outcomes alongside traditional performance metrics.

### Lightweight Language Models and Local Deployment

A technical strand of research supports the deployment of compact, locally-hosted language models with approximately 3 to 4 billion parameters for narrowly scoped tasks such as intent detection, task decomposition, structured output generation, and scheduling. These models are most effective when combined with schema constraints, function-calling mechanisms, and validation workflows. Research by Sharma and Mehta (2025) and Kavathekar and colleagues (2025) substantiate these approaches. Hermes 3 and similar instruction-tuned models demonstrate particularly strong performance in instruction following, structured JSON and XML output generation, and multi-turn dialogue capabilities suitable for iterative scheduling and function-call integration. Practitioner tools such as Ollama further reduce implementation complexity for local deployment and experimentation. Privacy and institutional data sovereignty literatures advocate for local, small-model deployments to maintain sensitive student data on campus infrastructure while preserving responsive, low-latency interactions.

### Integration and Synthesis

Collectively, these research findings justify the development of a privacy-first, locally-hosted student productivity system that integrates calendar-based planning and synchronization, deadline-aware prioritization, adaptive Pomodoro scaffolding, explainable LLM recommendations, and progress and well-being dashboards. TaskLyst operationalizes this research synthesis by combining a Hermes-powered conversational recommender hosted locally via Ollama, adaptive Pomodoro timers, visual workload balancing, and evaluation metrics that assess both task performance and student well-being outcomes. This integrated approach addresses documented gaps in existing tools by moving beyond passive task lists or cloud-dependent artificial intelligence systems to deliver an empirically grounded, deployable prototype tailored to the specific needs of university students while explicitly embedding transparency, user control, and data sovereignty principles in the system design.

---
