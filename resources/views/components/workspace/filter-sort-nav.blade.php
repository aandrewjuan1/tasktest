@props([
    'filterType' => null,
    'filterPriority' => null,
    'filterStatus' => null,
    'sortBy' => null,
    'sortDirection' => 'asc',
    'hasActiveFilters' => false,
    'filterDescription' => '',
    'sortDescription' => null,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <!-- Filter and Sort Controls Container -->
    <div class="flex items-center gap-1 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg px-2 py-1 border border-zinc-200 dark:border-zinc-700">
        <!-- Filter Button -->
        <flux:dropdown position="bottom" align="end">
            <flux:tooltip content="Filter items">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="funnel"
                    :class="$hasActiveFilters ? 'text-blue-600 dark:text-blue-400' : ''"
                    aria-label="Filter items"
                />
            </flux:tooltip>
            <flux:menu class="w-64">
                <flux:menu.heading>Item Type</flux:menu.heading>
                <flux:menu.radio.group>
                    <flux:menu.radio wire:click="$dispatch('set-filter-type', [null])" :checked="!$filterType || $filterType === 'all'">All</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-type', ['task'])" :checked="$filterType === 'task'">Task</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-type', ['event'])" :checked="$filterType === 'event'">Event</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-type', ['project'])" :checked="$filterType === 'project'">Project</flux:menu.radio>
                </flux:menu.radio.group>
                <flux:menu.separator />
                <flux:menu.heading>Priority</flux:menu.heading>
                <flux:menu.radio.group>
                    <flux:menu.radio wire:click="$dispatch('set-filter-priority', [null])" :checked="!$filterPriority || $filterPriority === 'all'">All</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-priority', ['urgent'])" :checked="$filterPriority === 'urgent'">Urgent</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-priority', ['high'])" :checked="$filterPriority === 'high'">High</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-priority', ['medium'])" :checked="$filterPriority === 'medium'">Medium</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-priority', ['low'])" :checked="$filterPriority === 'low'">Low</flux:menu.radio>
                </flux:menu.radio.group>
                <flux:menu.separator />
                <flux:menu.heading>Status</flux:menu.heading>
                <flux:menu.radio.group>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', [null])" :checked="!$filterStatus || $filterStatus === 'all'">All</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['to_do'])" :checked="$filterStatus === 'to_do'">To Do</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['doing'])" :checked="$filterStatus === 'doing'">Doing</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['ongoing'])" :checked="$filterStatus === 'ongoing'">Ongoing</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['done'])" :checked="$filterStatus === 'done'">Done</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['scheduled'])" :checked="$filterStatus === 'scheduled'">Scheduled</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['completed'])" :checked="$filterStatus === 'completed'">Completed</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['cancelled'])" :checked="$filterStatus === 'cancelled'">Cancelled</flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-filter-status', ['tentative'])" :checked="$filterStatus === 'tentative'">Tentative</flux:menu.radio>
                </flux:menu.radio.group>
            </flux:menu>
        </flux:dropdown>

        <!-- Sort Button -->
        <flux:dropdown position="bottom" align="end">
            <flux:tooltip content="Sort items">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="arrows-up-down"
                    :class="$sortBy ? 'text-blue-600 dark:text-blue-400' : ''"
                    aria-label="Sort items"
                />
            </flux:tooltip>
            <flux:menu class="w-56">
                <flux:menu.heading>Sort By</flux:menu.heading>
                <flux:menu.radio.group>
                    <flux:menu.radio wire:click="$dispatch('set-sort-by', ['priority'])" :checked="$sortBy === 'priority'">
                        Priority
                        @if($sortBy === 'priority')
                            <span class="ml-auto text-xs text-zinc-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-sort-by', ['created_at'])" :checked="$sortBy === 'created_at'">
                        Date Created
                        @if($sortBy === 'created_at')
                            <span class="ml-auto text-xs text-zinc-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-sort-by', ['start_datetime'])" :checked="$sortBy === 'start_datetime'">
                        Start Date
                        @if($sortBy === 'start_datetime')
                            <span class="ml-auto text-xs text-zinc-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-sort-by', ['end_datetime'])" :checked="$sortBy === 'end_datetime'">
                        End Date
                        @if($sortBy === 'end_datetime')
                            <span class="ml-auto text-xs text-zinc-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-sort-by', ['title'])" :checked="$sortBy === 'title'">
                        Title/Name
                        @if($sortBy === 'title')
                            <span class="ml-auto text-xs text-zinc-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </flux:menu.radio>
                    <flux:menu.radio wire:click="$dispatch('set-sort-by', ['status'])" :checked="$sortBy === 'status'">
                        Status
                        @if($sortBy === 'status')
                            <span class="ml-auto text-xs text-zinc-500">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </flux:menu.radio>
                </flux:menu.radio.group>
            </flux:menu>
        </flux:dropdown>
    </div>

    <!-- Active Filters and Sort Display -->
    @if($filterDescription || $sortDescription || $hasActiveFilters || $sortBy)
        <div class="flex items-center gap-2 px-3 py-1 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 text-sm text-zinc-600 dark:text-zinc-400">
            @if($filterDescription)
                <span class="font-medium">{{ $filterDescription }}</span>
            @endif
            @if($sortDescription)
                @if($filterDescription)
                    <span class="text-zinc-400 dark:text-zinc-500">•</span>
                @endif
                <span class="font-medium">{{ $sortDescription }}</span>
            @endif
            <!-- Clear All Button -->
            @if($hasActiveFilters || $sortBy)
                <flux:tooltip content="Clear all filters and sorts">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="x-mark"
                        wire:click="$dispatch('clear-all-filters-sorts')"
                        aria-label="Clear all filters and sorts"
                        class="ml-1"
                    />
                </flux:tooltip>
            @endif
        </div>
    @endif
</div>
