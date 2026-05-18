<x-admin-layout>
    <x-slot name="header">{{ __('alert.page_title') }} <x-help-icon topic="owntracks" /></x-slot>

    {{-- Summary badges --}}
    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.alerts.index', ['type' => 'missing_gps']) }}"
           class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ ($filters['type'] ?? '') === 'missing_gps' ? 'bg-red-600 text-white' : 'bg-red-100 text-red-800' }}">
            {{ __('alert.type_missing_gps') }}
            <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">{{ $counts['missing_gps'] }}</span>
        </a>
        <a href="{{ route('admin.alerts.index', ['type' => 'missing_weather']) }}"
           class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ ($filters['type'] ?? '') === 'missing_weather' ? 'bg-orange-600 text-white' : 'bg-orange-100 text-orange-800' }}">
            {{ __('alert.type_missing_weather') }}
            <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">{{ $counts['missing_weather'] }}</span>
        </a>
        <a href="{{ route('admin.alerts.index', ['type' => 'overdue']) }}"
           class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium {{ ($filters['type'] ?? '') === 'overdue' ? 'bg-yellow-600 text-white' : 'bg-yellow-100 text-yellow-800' }}">
            {{ __('alert.type_overdue') }}
            <span class="ml-1.5 inline-flex items-center justify-center rounded-full bg-white/20 px-2 py-0.5 text-xs font-bold">{{ $counts['overdue'] }}</span>
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.alerts.index') }}" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">{{ __('alert.filter_type') }}</label>
            <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">{{ __('alert.filter_type_all') }}</option>
                <option value="missing_gps" @selected(($filters['type'] ?? '') === 'missing_gps')>{{ __('alert.type_missing_gps') }}</option>
                <option value="missing_weather" @selected(($filters['type'] ?? '') === 'missing_weather')>{{ __('alert.type_missing_weather') }}</option>
                <option value="overdue" @selected(($filters['type'] ?? '') === 'overdue')>{{ __('alert.type_overdue') }}</option>
            </select>
        </div>
        <div>
            <label for="date_from" class="block text-sm font-medium text-gray-700">{{ __('alert.filter_date_from') }}</label>
            <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
        <div>
            <label for="date_to" class="block text-sm font-medium text-gray-700">{{ __('alert.filter_date_to') }}</label>
            <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
        </div>
        <div>
            <label for="status" class="block text-sm font-medium text-gray-700">{{ __('alert.filter_status') }}</label>
            <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="open" @selected(($filters['status'] ?? 'open') === 'open')>{{ __('alert.filter_status_open') }}</option>
                <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>{{ __('alert.filter_status_resolved') }}</option>
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('alert.filter_btn') }}
            </button>
            <a href="{{ route('admin.alerts.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('alert.filter_reset') }}</a>
        </div>
    </form>

    {{-- Alert list --}}
    <div class="mt-6">
        @if (!isset($filters['type']) || !$filters['type'])
            {{-- No type selected yet --}}
            <x-empty-state :heading="__('alert.select_type_heading')" :body="__('alert.select_type_body')" />
        @elseif ($alerts && $alerts->count())
            @php
                $isResolved = ($filters['status'] ?? '') === 'resolved';
            @endphp

            {{-- Bulk resolve button for open alerts --}}
            @if (!$isResolved && $alerts->count() > 1)
                <div class="mb-4">
                    <form method="POST" action="{{ route('admin.alerts.bulk-resolve') }}" x-data
                          x-on:submit.prevent="if(confirm('{{ __('alert.bulk_confirm') }}')) $el.submit()">
                        @csrf
                        <input type="hidden" name="type" value="{{ $filters['type'] }}">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 focus:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            {{ __('alert.btn_bulk_resolve') }} ({{ $counts[$filters['type']] ?? 0 }})
                        </button>
                    </form>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_job') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_customer') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_driver') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_type') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_date') }}</th>
                                @if ($isResolved)
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_resolved_at') }}</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_resolved_by') }}</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_note') }}</th>
                                @else
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('alert.col_actions') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($alerts as $alert)
                                @php
                                    $job = $isResolved ? $alert->job : $alert;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('admin.jobs.show', $job) }}" class="text-indigo-600 hover:text-indigo-900">
                                            #{{ $job->id }} — {{ $job->localStartedAt()->format('d.m.Y H:i') }}
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->customer->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @php
                                            $typeBadgeClass = match($filters['type']) {
                                                'missing_gps' => 'bg-red-100 text-red-800',
                                                'missing_weather' => 'bg-orange-100 text-orange-800',
                                                'overdue' => 'bg-yellow-100 text-yellow-800',
                                                default => 'bg-gray-100 text-gray-800',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $typeBadgeClass }}">
                                            {{ __('alert.type_' . $filters['type']) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->localStartedAt()->format('d.m.Y H:i') }}</td>
                                    @if ($isResolved)
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $alert->resolved_at->setTimezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $alert->resolvedBy?->name ?? '—' }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">{{ $alert->note ?? __('alert.no_note') }}</td>
                                    @else
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" x-data="{ showForm: false }">
                                            <button x-show="!showForm" x-on:click="showForm = true" type="button" class="text-indigo-600 hover:text-indigo-900">
                                                {{ __('alert.btn_resolve') }}
                                            </button>
                                            <div x-show="showForm" x-cloak class="text-left">
                                                <form method="POST" action="{{ route('admin.alerts.resolve', $job) }}">
                                                    @csrf
                                                    <input type="hidden" name="alert_type" value="{{ $filters['type'] }}">
                                                    <div class="mb-2">
                                                        <label class="block text-xs font-medium text-gray-700">{{ __('alert.resolve_note_label') }}</label>
                                                        <textarea name="note" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="{{ __('alert.resolve_note_placeholder') }}"></textarea>
                                                    </div>
                                                    <div class="flex gap-2">
                                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                            {{ __('alert.btn_resolve_submit') }}
                                                        </button>
                                                        <button type="button" x-on:click="showForm = false" class="text-xs text-gray-600 hover:text-gray-900 underline">
                                                            {{ __('alert.btn_resolve_cancel') }}
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $alerts->links() }}
            </div>
        @else
            <x-empty-state :heading="__('alert.empty_heading')" :body="__('alert.empty_filtered')" />
        @endif
    </div>
</x-admin-layout>
