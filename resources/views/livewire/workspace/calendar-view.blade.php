<div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex flex-col sticky top-6" x-cloak>
    <!-- Calendar Header -->
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
            {{ now()->format('F Y') }}
        </h2>
        <div class="flex items-center gap-2">
            <flux:button variant="ghost" size="sm" disabled>
                Today
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="chevron-left" disabled>
            </flux:button>
            <flux:button variant="ghost" size="sm" icon="chevron-right" disabled>
            </flux:button>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div>
        <!-- Day Headers -->
        <div class="grid grid-cols-7 gap-1 mb-2">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                <div class="text-center text-xs font-semibold text-zinc-600 dark:text-zinc-400 py-2">
                    {{ $day }}
                </div>
            @endforeach
        </div>

        <!-- Calendar Days -->
        <div class="grid grid-cols-7 gap-1">
            @php
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
                $firstDayOfWeek = $start->dayOfWeek;
                $current = $start->copy();
            @endphp

            {{-- Empty cells for days before the first day of the month --}}
            @for($i = 0; $i < $firstDayOfWeek; $i++)
                <div class="min-h-[80px] rounded-lg p-1 border border-transparent"></div>
            @endfor

            {{-- Days of the current month --}}
            @while($current <= $end)
                @php
                    $isToday = $current->isToday();
                    $borderClasses = $isToday ? 'border-2 border-green-500' : 'border border-zinc-200 dark:border-zinc-700';
                @endphp
                <div class="min-h-[80px] rounded-lg p-1 bg-white dark:bg-zinc-900 {{ $borderClasses }}">
                    <div class="text-xs text-zinc-900 dark:text-zinc-100 {{ $isToday ? 'font-bold text-green-600 dark:text-green-400' : '' }} mb-1">
                        {{ $current->day }}
                    </div>
                </div>
                @php
                    $current->addDay();
                @endphp
            @endwhile
        </div>
    </div>
</div>
