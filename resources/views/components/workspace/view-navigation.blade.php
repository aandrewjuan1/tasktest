@props([
    'viewMode' => 'list',
    'currentDate' => null,
    'weekStartDate' => null,
    'filterType' => null,
    'filterPriority' => null,
    'filterStatus' => null,
    'sortBy' => null,
    'sortDirection' => 'asc',
    'hasActiveFilters' => false,
    'mb' => false, // For kanban view which needs mb-4
    'noWrapper' => false, // For timegrid views which are already inside a container
])

@if(!$noWrapper)
<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden {{ $mb ? 'mb-4' : '' }}">
@endif
    <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
        <div class="flex items-center gap-3">
            <!-- View Switcher -->
            <div class="bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-700 p-1">
                <div class="flex gap-1" role="group" aria-label="View mode selection">
                    <flux:tooltip content="List View">
                        <button
                            @click="$wire.$parent.switchView('list')"
                            aria-label="Switch to list view"
                            aria-pressed="{{ $viewMode === 'list' ? 'true' : 'false' }}"
                            class="px-2 py-1.5 rounded transition-colors {{ $viewMode === 'list' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-transparent text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }} disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </flux:tooltip>
                    <flux:tooltip content="Kanban View">
                        <button
                            @click="$wire.$parent.switchView('kanban')"
                            aria-label="Switch to kanban view"
                            aria-pressed="{{ $viewMode === 'kanban' ? 'true' : 'false' }}"
                            class="px-2 py-1.5 rounded transition-colors {{ $viewMode === 'kanban' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-transparent text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }} disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                        </button>
                    </flux:tooltip>
                    <flux:tooltip content="Timegrid View">
                        <button
                            @click="$wire.$parent.switchView('daily-timegrid')"
                            aria-label="Switch to timegrid view"
                            aria-pressed="{{ in_array($viewMode, ['daily-timegrid', 'weekly-timegrid']) ? 'true' : 'false' }}"
                            class="px-2 py-1.5 rounded transition-colors {{ in_array($viewMode, ['daily-timegrid', 'weekly-timegrid']) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-transparent text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600' }} disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </flux:tooltip>
                </div>
            </div>

            <!-- Date/Week Display -->
            @if($viewMode === 'weekly-timegrid' && $weekStartDate)
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $weekStartDate->format('M d') }} - {{ $weekStartDate->copy()->addDays(6)->format('M d, Y') }}
                </h3>
            @elseif($viewMode === 'daily-timegrid')
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ ($currentDate ?? now())->format('M d, Y') }}
                </h3>
            @elseif($viewMode === 'weekly')
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ ($weekStartDate ?? now()->startOfWeek())->format('M d') }} - {{ ($weekStartDate ?? now()->startOfWeek())->copy()->addDays(6)->format('M d, Y') }}
                </h3>
            @else
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ ($currentDate ?? now())->format('M d, Y') }}
                </h3>
            @endif

            <!-- Date/Week Navigation -->
            @if($viewMode === 'weekly-timegrid')
                <div class="flex items-center gap-2" role="group" aria-label="Week navigation">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="goToToday"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousWeek,nextWeek"
                        aria-label="Go to current week"
                    >
                        <span wire:loading.remove wire:target="goToToday,previousWeek,nextWeek">Today</span>
                        <span wire:loading wire:target="goToToday,previousWeek,nextWeek">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        wire:click="previousWeek"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousWeek,nextWeek"
                        aria-label="Previous week"
                    >
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-right"
                        wire:click="nextWeek"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousWeek,nextWeek"
                        aria-label="Next week"
                    >
                    </flux:button>
                </div>
            @elseif($viewMode === 'daily-timegrid')
                <div class="flex items-center gap-2" role="group" aria-label="Date navigation">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="goToToday"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousDay,nextDay"
                        aria-label="Go to today"
                    >
                        <span wire:loading.remove wire:target="goToToday,previousDay,nextDay">Today</span>
                        <span wire:loading wire:target="goToToday,previousDay,nextDay">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        wire:click="previousDay"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousDay,nextDay"
                        aria-label="Previous day"
                    >
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-right"
                        wire:click="nextDay"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousDay,nextDay"
                        aria-label="Next day"
                    >
                    </flux:button>
                </div>
            @elseif($viewMode === 'weekly')
                <div class="flex items-center gap-2" role="group" aria-label="Week navigation">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        wire:click="goToToday"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousWeek,nextWeek"
                        aria-label="Go to current week"
                    >
                        <span wire:loading.remove wire:target="goToToday,previousWeek,nextWeek">Today</span>
                        <span wire:loading wire:target="goToToday,previousWeek,nextWeek">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        wire:click="previousWeek"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousWeek,nextWeek"
                        aria-label="Previous week"
                    >
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-right"
                        wire:click="nextWeek"
                        wire:loading.attr="disabled"
                        wire:target="goToToday,previousWeek,nextWeek"
                        aria-label="Next week"
                    >
                    </flux:button>
                </div>
            @else
                <div class="flex items-center gap-2"
                     role="group"
                     aria-label="Date navigation"
                     x-data="{
                         navigationTimeout: null,
                         navigateDate(action) {
                             clearTimeout(this.navigationTimeout);
                             this.navigationTimeout = setTimeout(() => {
                                 if (action === 'today') {
                                     $wire.$parent.goToTodayDate();
                                 } else if (action === 'previous') {
                                     $wire.$parent.previousDay();
                                 } else if (action === 'next') {
                                     $wire.$parent.nextDay();
                                 }
                             }, 150);
                         }
                     }">
                    <flux:button
                        variant="ghost"
                        size="sm"
                        @click="navigateDate('today')"
                        wire:loading.attr="disabled"
                        wire:target="goToTodayDate,previousDay,nextDay"
                        aria-label="Go to today"
                    >
                        <span wire:loading.remove wire:target="updateCurrentDate">Today</span>
                        <span wire:loading wire:target="updateCurrentDate">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-left"
                        @click="navigateDate('previous')"
                        wire:loading.attr="disabled"
                        wire:target="goToTodayDate,previousDay,nextDay"
                        aria-label="Previous day"
                    >
                    </flux:button>
                    <flux:button
                        variant="ghost"
                        size="sm"
                        icon="chevron-right"
                        @click="navigateDate('next')"
                        wire:loading.attr="disabled"
                        wire:target="goToTodayDate,previousDay,nextDay"
                        aria-label="Next day"
                    >
                    </flux:button>
                </div>
            @endif
        </div>

        <!-- Filter and Sort Container -->
        <x-workspace.filter-sort-nav
            :filter-type="$filterType"
            :filter-priority="$filterPriority"
            :filter-status="$filterStatus"
            :sort-by="$sortBy"
            :sort-direction="$sortDirection"
            :has-active-filters="$hasActiveFilters"
        />
    </div>
@if(!$noWrapper)
</div>
@endif
