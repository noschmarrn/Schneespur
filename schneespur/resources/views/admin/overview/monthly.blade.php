<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_overview_monthly') }} <x-help-icon topic="overview" /></x-slot>

    {{-- Month navigation --}}
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('admin.overview.monthly', ['month' => $prevMonth->format('Y-m')]) }}"
           class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
            {{ __('overview.prev') }}
        </a>

        <div class="flex items-center gap-3">
            <span class="text-lg font-semibold text-gray-900">
                {{ $month->locale(app()->getLocale())->isoFormat('MMMM YYYY') }}
            </span>

            @unless($month->isSameMonth(now()))
                <a href="{{ route('admin.overview.monthly') }}"
                   class="inline-flex items-center px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
                    {{ __('overview.this_month') }}
                </a>
            @endunless

            @if($activeMonths->count() > 1)
                <select onchange="if(this.value) window.location='{{ route('admin.overview.monthly') }}?month='+this.value"
                        class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">{{ __('overview.jump_to_month') }}</option>
                    @foreach($activeMonths as $key => $am)
                        <option value="{{ $key }}" {{ $month->format('Y-m') === $key ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($key . '-01')->locale(app()->getLocale())->isoFormat('MMMM YYYY') }} ({{ $am->job_count }})
                        </option>
                    @endforeach
                </select>
            @endif
        </div>

        <a href="{{ route('admin.overview.monthly', ['month' => $nextMonth->format('Y-m')]) }}"
           class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 transition">
            {{ __('overview.next') }}
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
        </a>
    </div>

    @if($monthTotal > 0)
        {{-- Summary bar --}}
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6 mb-6">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.total_jobs') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $monthTotal }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.total_duration') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">
                        @php $h = intdiv($totalMinutes, 60); $m = $totalMinutes % 60; @endphp
                        {{ $h > 0 ? $h . 'h ' . $m . 'min' : $m . 'min' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('overview.active_drivers') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $activeDriverCount }}</dd>
                </div>
            </div>
        </div>

        {{-- Calendar grid with Alpine.js drill-down --}}
        <div x-data="{ expandedDay: null, dayHtml: '', loading: false }" class="bg-white overflow-hidden shadow-sm rounded-lg">
            @php
                $firstDay = $month->copy()->startOfMonth();
                $startDow = $firstDay->dayOfWeekIso;
                $daysInMonth = $month->daysInMonth;
                $leadingBlanks = $startDow - 1;
                $today = now()->format('Y-m-d');
            @endphp

            {{-- Day name headers --}}
            <div class="grid grid-cols-7 border-b border-gray-200">
                @foreach(['day_mo', 'day_di', 'day_mi', 'day_do', 'day_fr', 'day_sa', 'day_so'] as $dayKey)
                    <div class="py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        {{ __('overview.' . $dayKey) }}
                    </div>
                @endforeach
            </div>

            {{-- Calendar cells --}}
            <div class="grid grid-cols-7">
                {{-- Leading blank cells --}}
                @for($i = 0; $i < $leadingBlanks; $i++)
                    <div class="min-h-[4rem] border-b border-r border-gray-100 bg-gray-50"></div>
                @endfor

                {{-- Day cells --}}
                @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateStr = $month->format('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                        $count = $dailyCounts->get($dateStr)?->job_count ?? 0;
                        $isToday = $dateStr === $today;
                        $isFuture = $dateStr > $today;
                        $cellPos = $leadingBlanks + $day;
                        $isLastRow = $cellPos > ($daysInMonth + $leadingBlanks - 7);
                    @endphp
                    <div
                        @if($count > 0)
                            @click="
                                if (expandedDay === '{{ $dateStr }}') {
                                    expandedDay = null;
                                    dayHtml = '';
                                } else {
                                    expandedDay = '{{ $dateStr }}';
                                    loading = true;
                                    dayHtml = '';
                                    fetch('{{ route('admin.overview.day-detail') }}?date={{ $dateStr }}')
                                        .then(r => r.text())
                                        .then(html => { dayHtml = html; loading = false; })
                                        .catch(() => { loading = false; });
                                }
                            "
                        @endif
                        class="min-h-[4rem] p-2 border-b border-r border-gray-100 flex flex-col items-start
                            {{ $count > 0 ? 'bg-blue-50 cursor-pointer hover:bg-blue-100 transition-colors' : '' }}
                            {{ $isToday ? 'ring-2 ring-inset ring-blue-500' : '' }}
                            {{ $isFuture && $count === 0 ? 'text-gray-400' : '' }}"
                    >
                        <span class="text-sm font-medium {{ $isToday ? 'text-blue-700 font-bold' : ($isFuture && $count === 0 ? 'text-gray-400' : 'text-gray-700') }}">
                            {{ $day }}
                        </span>
                        @if($count > 0)
                            <span class="mt-1 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                {{ $count }}
                            </span>
                        @endif
                    </div>
                @endfor

                {{-- Trailing blank cells --}}
                @php $trailingBlanks = (7 - (($leadingBlanks + $daysInMonth) % 7)) % 7; @endphp
                @for($i = 0; $i < $trailingBlanks; $i++)
                    <div class="min-h-[4rem] border-b border-r border-gray-100 bg-gray-50"></div>
                @endfor
            </div>

            {{-- Inline drill-down area --}}
            <div x-show="expandedDay" x-cloak class="border-t border-gray-200">
                <template x-if="loading">
                    <div class="flex items-center justify-center py-8">
                        <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="ml-2 text-sm text-gray-500">{{ __('overview.loading') }}</span>
                    </div>
                </template>
                <template x-if="!loading && dayHtml">
                    <div x-html="dayHtml" class="p-4"></div>
                </template>
            </div>
        </div>
    @else
        <x-empty-state :heading="__('overview.no_jobs_month')" :body="__('overview.no_jobs_month_hint')" />
    @endif
</x-admin-layout>
