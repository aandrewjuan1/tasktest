# LLM Task Management Integration Workflow
## High-Level Reference Guide for TaskLyst Implementation

---

## Core Workflow Overview

```
User Natural Language Input
    ↓
Intent Classification (Fast - No LLM)
    ↓
Route to Appropriate Handler
    ↓
Context Preparation (Minimal, Surgical)
    ↓
LLM Inference (Hermes 3 3B)
    ↓
Structured Output (JSON with Reasoning)
    ↓
Display to User (Explainable Recommendations)
    ↓
User Action (Accept/Modify/Reject)
    ↓
Backend Execution (Validation → Database Update)
    ↓
Feedback Loop & Logging
```

---

## Phase 1: Intent Classification

### Purpose
Determine what the user is trying to do **without calling the LLM**. This is the gatekeeper that prevents unnecessary AI inference.

### What Happens
- Input: User's natural language request
- Processing: Lightweight regex pattern matching + keyword detection
- Output: Intent type + confidence score

### Intent Types
1. **schedule_task** - User wants to time a specific task
   - Keywords: "finish", "by", "deadline", "schedule", "when"
   - Example: "Schedule my dashboard by Friday"

2. **prioritize_tasks** - User wants to rank/order tasks
   - Keywords: "priority", "important", "urgent", "rank", "order"
   - Example: "What should I focus on today?"

3. **resolve_dependency** - User wants to manage task blocking
   - Keywords: "blocked", "waiting", "depends", "after"
   - Example: "I'm blocked on the API integration"

4. **adjust_deadline** - User wants to change task timing
   - Keywords: "extend", "move", "delay", "push", "earlier"
   - Example: "Can we push the dashboard deadline to next week?"

5. **general_query** - Doesn't match other categories
   - Fallback for unclear requests

### Why This Step Matters
- ✅ **Speed**: Instant decision (<10ms) vs. waiting for LLM
- ✅ **Cost**: No token usage for simple operations
- ✅ **Reliability**: Deterministic, no hallucination risk
- ✅ **User Experience**: Fast initial feedback

### Success Criteria
- Classification accuracy >90% on test cases
- Confidence scores meaningful and calibrated
- Fallback to `general_query` gracefully when uncertain

---

## Phase 2: Context Preparation

### Purpose
Gather **only the necessary data** to help Hermes 3 make an informed decision. More context = slower + more tokens + higher hallucination risk.

### What Happens
Query the database for relevant information tailored to the detected intent.

### Context Structure by Intent

#### For `schedule_task` Intent
```
Required Data:
- Target task details (title, description, deadline, duration estimate)
- User work preferences (timezone, work hours, focus times)
- Blocking tasks (what must complete before this task)
- Dependent tasks (what depends on this completing)
- Recent similar tasks (for effort estimation patterns)

Maximum: 5-10 tasks total
Maximum tokens: ~800-1000
```

#### For `prioritize_tasks` Intent
```
Required Data:
- All pending/scheduled tasks (limited to 10 most urgent)
- Each task: deadline, estimated duration, priority, dependencies
- User work patterns (productivity peaks, context switch limits)
- Current date/time context

Maximum: 10 tasks total
Maximum tokens: ~1200
```

#### For `resolve_dependency` Intent
```
Required Data:
- Currently blocked task details
- Blocking task status and estimated completion
- Dependent tasks waiting on this one
- Critical path analysis

Maximum: 3-5 related tasks
Maximum tokens: ~600
```

#### For `adjust_deadline` Intent
```
Required Data:
- Task being adjusted
- Current deadline vs. requested deadline
- Dependent tasks affected
- User availability in new timeframe

Maximum: 5 tasks
Maximum tokens: ~700
```

### Context Filtering Rules
✅ **Include:**
- Active task data (pending, scheduled, in_progress)
- User preferences and patterns
- Direct dependencies only
- Recent historical patterns (last 30 days)

❌ **Exclude:**
- Completed tasks (unless showing patterns)
- All 100+ tasks in the backlog
- Irrelevant metadata
- Full task descriptions if not needed
- Tasks older than 90 days
- Archive data

### Why This Step Matters
- ✅ **Token Efficiency**: 3B model has limited context window (~8K-32K tokens)
- ✅ **Accuracy**: LLM focuses on relevant data, less noise
- ✅ **Speed**: Smaller payloads = faster inference
- ✅ **Cost**: Fewer tokens = lower resource usage

### Success Criteria
- Context payload <1500 tokens consistently
- Includes all necessary decision factors
- Excludes irrelevant noise
- Preparation completes in <150ms

---

## Phase 3: System Prompting

### Purpose
Set up Hermes 3 to understand its role, constraints, and expected output format.

### System Prompt Structure

Each intent gets a **specific, tailored system prompt** (not generic).

#### Template Components
1. **Role Definition**
   - "You are an intelligent task scheduling assistant"
   - "Your goal is to prioritize tasks based on X, Y, Z"

2. **Analysis Framework**
   - Step-by-step reasoning process
   - What to consider first, second, third
   - How to evaluate tradeoffs

3. **User Context**
   - Work patterns to respect
   - Preferences to honor
   - Constraints to observe

4. **Output Requirements**
   - Response format (JSON only, no markdown)
   - Required fields
   - No extra text before/after JSON

5. **Tone & Behavior**
   - Be concise
   - Be confident but humble
   - Explain reasoning clearly

### System Prompt Examples

#### For Scheduling
```
Role: Task scheduling assistant for a developer

Goal: Suggest optimal time slots that respect:
- Deadlines and hard constraints
- Task dependencies
- User's work patterns (morning focus, context switches)
- Effort estimation

Analysis steps:
1. When is the deadline?
2. What tasks block this one?
3. How long will this realistically take?
4. What's the optimal time considering user patterns?
5. Check for conflicts and buffer time

Output: JSON with suggested date/time and reasoning
```

#### For Prioritization
```
Role: Task prioritization expert

Goal: Rank tasks by true urgency, considering:
- Deadline proximity
- Task dependencies (what blocks others)
- Effort vs. urgency (RICE scoring)
- User's work patterns and capacity

Analysis steps:
1. Identify hard deadline constraints
2. Map dependency graph
3. Calculate impact of each task
4. Determine priority scores (0-100)
5. Highlight critical blockers

Output: JSON with prioritized list and reasoning
```

### Prompt Best Practices
- ✅ Be specific about output format
- ✅ Provide step-by-step reasoning framework
- ✅ Include user context inline
- ✅ Set temperature to 0.3 (low creativity, high consistency)
- ✅ Limit output tokens to 500 (concise responses)
- ❌ Don't use vague language
- ❌ Don't ask for multiple response formats
- ❌ Don't include conflicting instructions

### Success Criteria
- Output is always valid JSON
- Reasoning is transparent and logical
- Recommendations are actionable
- Temperature 0.3 produces consistent results

---

## Phase 4: LLM Inference

### Purpose
Send minimal context + structured prompt to Hermes 3 and receive structured recommendations.

### What Happens
1. **Format Request**
   - System prompt (role + analysis framework)
   - User input (natural language request)
   - Context (minimal, curated data from Phase 2)

2. **Call Ollama/Hermes 3**
   - Model: `hermes2:3.1b` or `hermes2:7b`
   - Temperature: 0.3
   - Max tokens: 500
   - Timeout: 30 seconds

3. **Parse Response**
   - Extract JSON from response (handle markdown code blocks)
   - Validate structure
   - Handle parsing errors gracefully

4. **Log Interaction**
   - Tokens used
   - Processing time
   - Response quality indicator
   - User ID + intent type

### Inference Parameters

| Parameter | Value | Why |
|-----------|-------|-----|
| Model | hermes2:3.1b | Lightweight, fast, local |
| Temperature | 0.3 | Consistent, deterministic |
| Top K | 20 | Prevent random outputs |
| Max Tokens | 500 | Avoid rambling |
| Timeout | 30s | Prevent hanging |

### Response Structure
Hermes 3 should always return structured JSON:

```json
{
  "task_id": 123,
  "recommended_action": "string",
  "reasoning": "Step 1: ... Step 2: ... Step 3: ...",
  "confidence": 0.85,
  "blockers": ["issue 1", "issue 2"],
  "alternative_options": [
    { "option": "...", "tradeoff": "..." }
  ]
}
```

### Error Handling
- If JSON parsing fails → Use fallback recommendation
- If response times out → Show cached previous result
- If Ollama is down → Activate rule-based fallback
- If confidence <0.6 → Flag for human review

### Success Criteria
- Response always valid JSON
- Processing time <3-5 seconds
- Reasoning includes concrete facts (not vague)
- All required fields present
- Confidence score provided

---

## Phase 5: Structured Output Display

### Purpose
Show the user **both the recommendation AND the reasoning** in a transparent, understandable format.

### Display Components

#### Primary Recommendation
- Clear visual hierarchy
- Key metrics at top (date, time, priority)
- Color coding for urgency/confidence
- Not overwhelming with information

#### Reasoning Section
- Show step-by-step logic
- Reference concrete facts from context
- Explain tradeoffs considered
- Format: "Step 1: X. Step 2: Y. Step 3: Z."

#### Blockers/Dependencies
- List items blocking this task
- Current status of blockers
- Estimated completion time
- Yellow/red warning colors

#### Smart Suggestions
- 2-3 actionable recommendations
- Based on LLM's analysis
- Examples: "Schedule this Tuesday morning for best results"
- Format: Bullet points with checkmarks

#### Confidence Indicator
- 0-100 score
- Visual representation (bar, color)
- Interpretation guide ("Very confident", "Moderate", etc.)

### Layout Principles
- ✅ Information hierarchy (most important first)
- ✅ Visual separations between sections
- ✅ Color coding for quick scanning
- ✅ Mobile-friendly responsive design
- ✅ Clear call-to-action buttons

### User Feedback Elements
- **Accept** button (green, primary action)
- **Modify** button (grey, secondary)
- **Reject/Try Again** button (subtle)
- Show processing state ("Generating...", spinner)
- Show success state ("Scheduled!", checkmark)

### Success Criteria
- User understands recommendation at a glance
- Reasoning is transparent and not magical
- User has clear action options
- Design builds trust, not confusion

---

## Phase 6: User Validation & Action

### Purpose
User reviews the recommendation and decides to accept, modify, or reject it.

### User Actions

#### Option 1: Accept
- User clicks "✅ Accept" button
- Proceeds to Phase 7 (Backend Execution)
- No modification needed

#### Option 2: Modify
- User adjusts parameters (date, time, priority)
- LLM recommendation is overridden
- User's input takes precedence
- Still proceeds to Phase 7 with modified values

#### Option 3: Reject
- User clicks "Try Again" or "Cancel"
- Reverts to input form
- Can enter new request or modify input
- Starts workflow over from Phase 1

### Modification Workflow
If user modifies recommendation:
1. User sees input fields for: date, time, duration, priority
2. User changes one or more values
3. Modified values override LLM suggestion
4. Display updated recommendation
5. Show warning if modification creates conflicts
6. Proceed to backend execution with modified values

### Why This Step Matters
- ✅ **User Control**: LLM is assistant, not dictator
- ✅ **Safety**: Prevents automatic bad decisions
- ✅ **Trust**: User sees and approves changes
- ✅ **Feedback**: User modifications inform future improvements
- ✅ **Flexibility**: Humans catch edge cases LLM misses

### Success Criteria
- User understands the recommendation
- User has clear options (accept/modify/reject)
- Modification flow is smooth and intuitive
- Changes are reflected immediately

---

## Phase 7: Backend Execution

### Purpose
**Now the system makes actual database changes** (not before!). Backend is the source of truth.

### Validation Layer
Before any database change:
1. **Syntax Validation**
   - Dates are valid format
   - Times are within work hours
   - Durations are reasonable (>0, <12 hours typically)

2. **Business Logic Validation**
   - No scheduling conflicts
   - Dependencies are met/ordered correctly
   - User capacity not exceeded
   - Deadline is respected

3. **Data Integrity Checks**
   - Task exists and belongs to user
   - No race conditions
   - Concurrent modification safe

### Execution Steps
1. **Validate** recommendation against rules
2. **Update** task in database
   - scheduled_date
   - scheduled_time
   - estimated_duration
   - priority_score
   - status (pending → scheduled)
   - llm_reasoning (store for audit trail)

3. **Cascade Updates**
   - If dependent tasks affected, re-prioritize them
   - Update blockers relationships
   - Notify dependent task owners if shared workspace

4. **Log Changes**
   - Store in audit log
   - Record: what changed, why (LLM reasoning), when, by whom
   - For analytics: track LLM recommendation accuracy

5. **Notify User**
   - Show success message
   - Display updated task view
   - Optionally: show next recommended action

### Database Transactions
- ✅ Use transactions for multi-step updates
- ✅ Rollback on any validation failure
- ✅ Prevent partial updates

### API/Response Structure
```
{
  "success": true,
  "message": "Task scheduled for Wednesday, Dec 10 at 9:00 AM",
  "task": {
    "id": 123,
    "title": "...",
    "scheduled_date": "2025-12-10",
    "scheduled_time": "09:00",
    "priority_score": 92,
    "status": "scheduled"
  },
  "next_action": "Your next recommended task is..."
}
```

### Error Responses
```
{
  "success": false,
  "error": "Scheduling conflict detected",
  "details": "Task overlaps with 'API Integration' already scheduled for 9am-5pm",
  "suggestion": "Try scheduling for Thursday instead"
}
```

### Why This Step Matters
- ✅ **Data Integrity**: All changes validated before commit
- ✅ **Audit Trail**: Track what LLM recommended vs. what actually happened
- ✅ **Rollback Capability**: Undo changes if needed
- ✅ **Monitoring**: Detect if LLM makes consistently bad recommendations

### Success Criteria
- All database changes are validated
- Transactions ensure consistency
- Audit trail is complete
- User gets clear feedback on success/failure

---

## Phase 8: Feedback Loop & Logging

### Purpose
Track LLM performance, user acceptance, and gather data to improve future recommendations.

### What Gets Logged

#### Per Interaction
- **User ID** - who made the request
- **Intent Type** - what they were trying to do
- **Input Context** - what data was sent to LLM
- **LLM Response** - what the model returned
- **Tokens Used** - for cost tracking
- **Processing Time** - for performance monitoring
- **User Action** - did they accept/modify/reject?

#### Metrics Tracked
- **LLM Accuracy** - did user accept the recommendation?
- **Token Efficiency** - how many tokens per decision type?
- **Latency** - how fast was the full workflow?
- **Error Rate** - how often did parsing/inference fail?
- **User Satisfaction** - do users modify recommendations often?

### Analytics Dashboard (Future)
Questions to answer with logs:
- Which intent types have highest acceptance rate?
- What time of day are recommendations most accurate?
- How does recommendation quality correlate with context size?
- Are certain user types accepting recommendations more?
- What's the most common modification pattern?

### Continuous Improvement
Use logged data to:
1. **Refine prompts** - If acceptance <70%, tweak system prompt
2. **Adjust confidence** - Calibrate confidence scores against actual acceptance
3. **Tune parameters** - Find optimal temperature, max_tokens, context size
4. **Detect edge cases** - Find patterns in rejections, address them

### Success Criteria
- All interactions logged consistently
- Logs contain both input and output
- Processing times tracked
- User acceptance data recorded
- Data structure supports future analysis

---

## Complete Workflow Decision Tree

```
User Input
├─ IntentClassifier
│  ├─ schedule_task (90% conf) → Schedule handler
│  ├─ prioritize_tasks (85% conf) → Prioritize handler
│  ├─ resolve_dependency (70% conf) → Dependency handler
│  ├─ adjust_deadline (80% conf) → Deadline handler
│  └─ general_query (?) → Help message
│
├─ ContextPreparer
│  └─ Fetch minimal relevant data from DB
│
├─ OllamaService
│  ├─ System Prompt (intent-specific)
│  ├─ Context (capped at ~1000 tokens)
│  └─ Hermes 3 Inference (temp 0.3, max 500 tokens)
│
├─ Response Parser
│  ├─ Extract JSON from response
│  ├─ Validate structure
│  └─ Fallback if parsing fails
│
├─ Display Layer
│  ├─ Render recommendation
│  ├─ Show reasoning
│  ├─ Highlight blockers
│  └─ Provide action buttons
│
├─ User Action
│  ├─ Accept → Backend execution
│  ├─ Modify → Updated backend execution
│  └─ Reject → Reset form
│
└─ Backend + Logging
   ├─ Validate all changes
   ├─ Update database
   ├─ Log interaction
   └─ Show success/failure
```

---

## Performance Targets

| Stage | Duration | Target |
|-------|----------|--------|
| Intent Classification | <10ms | Instant, no waiting |
| Context Preparation | 50-150ms | Fast DB queries |
| LLM Inference | 1-3 seconds | Acceptable wait |
| Display Rendering | <100ms | Instant |
| **Total Round Trip** | **2-4 seconds** | **Snappy UX** |

---

## Fallback Strategy

If LLM fails at any point:

1. **LLM Timeout** → Use cached previous recommendation
2. **JSON Parse Error** → Show generic suggestion + "Try rephrasing"
3. **Ollama Offline** → Activate rule-based prioritization
4. **Confidence <60%** → Flag for manual review
5. **Unknown Intent** → Show help examples

Rule-based fallback logic:
- Prioritize by deadline only
- Schedule by availability only
- Do NOT make assumptions

---

## Key Design Principles

### 1. Progressive Disclosure
- Show what matters first (recommendation)
- Details available on demand (reasoning)
- Don't overwhelm user with data

### 2. Trust Through Transparency
- Always show why LLM made a decision
- Include concrete facts in reasoning
- Admit uncertainty via confidence scores

### 3. User Control
- LLM suggests, user approves
- Easy modification options
- User can always override

### 4. Fail Gracefully
- Errors are not crashes
- Fallback to simple rules
- Clear error messages

### 5. Efficient Context
- Less is more (minimal context window)
- Every token counts (cost + quality)
- Curate ruthlessly

### 6. Intent First
- Don't call LLM for simple decisions
- Classify before computing
- Route intelligently

### 7. Structured Over Free-Form
- JSON output, never free text
- Parseable, predictable format
- Prevents hallucination

### 8. Audit Everything
- Log all decisions
- Store reasoning
- Enable continuous improvement

---

## Implementation Checklist

### Phase 1: Intent Classification
- [ ] Define regex patterns for each intent
- [ ] Test classification accuracy >90%
- [ ] Implement confidence scoring
- [ ] Add fallback to `general_query`

### Phase 2: Context Preparation
- [ ] Design context schema per intent type
- [ ] Implement data filtering (max 10 tasks)
- [ ] Set token budget (~1000 max)
- [ ] Add caching for user preferences

### Phase 3: System Prompting
- [ ] Write intent-specific system prompts
- [ ] Test with Hermes 3 locally
- [ ] Tune temperature (0.3 recommended)
- [ ] Validate JSON output format

### Phase 4: LLM Inference
- [ ] Setup Ollama client
- [ ] Implement error handling
- [ ] Add request logging
- [ ] Setup performance monitoring

### Phase 5: Display Layer
- [ ] Design recommendation card UI
- [ ] Implement reasoning display
- [ ] Add blocker alerts
- [ ] Create action buttons

### Phase 6: User Validation
- [ ] Build accept/modify/reject buttons
- [ ] Implement modify form
- [ ] Add conflict detection
- [ ] Show real-time updates

### Phase 7: Backend Execution
- [ ] Validation layer (dates, conflicts)
- [ ] Database update transactions
- [ ] Cascade updates for dependencies
- [ ] Audit logging

### Phase 8: Analytics
- [ ] Log all interactions
- [ ] Track acceptance rates
- [ ] Monitor token usage
- [ ] Setup performance dashboard

---

## Common Pitfalls to Avoid

❌ **Don't:** Send all 100 tasks to LLM
✅ **Do:** Send 5-10 most relevant tasks

❌ **Don't:** Use free-form LLM responses
✅ **Do:** Force structured JSON output

❌ **Don't:** Execute recommendations without user approval
✅ **Do:** Show user first, get approval, then execute

❌ **Don't:** Hide the LLM reasoning
✅ **Do:** Show step-by-step logic transparently

❌ **Don't:** Call LLM for every input
✅ **Do:** Classify first, route intelligently

❌ **Don't:** Ignore LLM failures
✅ **Do:** Implement fallback rules

❌ **Don't:** Use high temperature (>0.7)
✅ **Do:** Use temperature 0.3 for consistency

❌ **Don't:** Skip logging/monitoring
✅ **Do:** Log everything for continuous improvement

---

## Success Metrics

### User Experience
- Workflow completes in <5 seconds
- Recommendation is understood immediately
- User satisfaction >80% (accept rate >80%)
- Mobile-friendly, no scroll needed

### System Performance
- Intent classification: <10ms
- Context prep: <150ms
- LLM inference: <3s (acceptable)
- Total: <4 seconds

### LLM Quality
- Output is valid JSON 99%+ of time
- Reasoning is transparent and logical
- Confidence scores are calibrated
- Accuracy improves over time (feedback loop)

### Business Metrics
- User task completion rate +30%
- Time spent scheduling -70%
- Error rate in scheduling -50%
- Users adopt feature within first week

---

## Next Steps (Roadmap)

### Phase 1 (Current)
- [ ] Implement basic scheduling (schedule_task)
- [ ] Implement basic prioritization (prioritize_tasks)

### Phase 2
- [ ] Add dependency resolution (resolve_dependency)
- [ ] Add deadline adjustment (adjust_deadline)

### Phase 3
- [ ] Multi-user collaboration (team scheduling)
- [ ] Recurring tasks
- [ ] Time zone intelligence

### Phase 4
- [ ] ML-based effort estimation
- [ ] Predictive scheduling (predict completion)
- [ ] Calendar integration

### Phase 5
- [ ] Cross-workspace dependencies
- [ ] Resource allocation
- [ ] Burndown projections

---

## Reference Links & Resources

### Local LLM
- Ollama: https://ollama.ai
- Hermes 2 Model: huggingface.co/NousResearch/Hermes-2-Pro
- LocalAI Alternative: https://localai.io

### Frameworks
- Laravel 12: https://laravel.com/docs/12.x
- Livewire 3: https://livewire.laravel.com/docs/3.x
- PrismPHP: https://prismphp.com (advanced, optional)

### Prompt Engineering
- Anthropic Prompt Guide: https://docs.anthropic.com/claude/reference/prompt-engineering
- OpenAI Best Practices: https://platform.openai.com/docs/guides/prompt-engineering
- Few-shot Learning: Common prompt patterns

### Performance
- Measure token usage per request
- Monitor inference latency
- Track user acceptance rate
- Log error rates

---

## Questions to Ask During Implementation

1. **Intent Classification**: Am I covering all user intents? What edge cases am I missing?
2. **Context**: Is my context minimal enough? Could I remove any fields?
3. **Prompting**: Is my system prompt clear? Does Hermes 3 understand the task?
4. **Output**: Is JSON always valid? What happens when it's not?
5. **Display**: Can a first-time user understand the recommendation?
6. **User Action**: Is the accept/modify/reject flow intuitive?
7. **Backend**: Am I validating all inputs before database writes?
8. **Logging**: Am I capturing enough data to improve the system?

---

## Conclusion

This workflow balances **intelligence (LLM reasoning) with control (user approval)** and **speed (lightweight, minimal context) with quality (structured output, transparent reasoning)**.

The key insight: **LLM should augment, not replace, your business logic.**

- Use LLM for what it's good at: understanding context, ranking options, detecting patterns
- Use rules for what's critical: validation, safety, non-negotiable constraints
- Use user for what only they can do: making final decisions, handling exceptions

This creates a **trustworthy, performant, scalable system** that users will actually adopt and benefit from.

Good luck with your thesis! 🚀
