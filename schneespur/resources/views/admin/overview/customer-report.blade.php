<x-admin-layout>
    <x-slot name="header">{{ __('admin.page_overview_customer_report') }} <x-help-icon topic="overview" /></x-slot>

    {{-- Filter bar --}}
    <div class="bg-white shadow rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('admin.overview.customer-report') }}" class="flex flex-wrap items-end gap-4">
            {{-- Customer select --}}
            <div class="flex-1 min-w-[180px]">
                <label for="customer" class="block text-sm font-medium text-gray-700 mb-1">{{ __('overview.customer_report_select_customer') }}</label>
                <select name="customer" id="customer" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">{{ __('overview.customer_report_select_customer') }}</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected($selectedCustomer && $selectedCustomer->id === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date from --}}
            <div>
                <label for="from" class="block text-sm font-medium text-gray-700 mb-1">{{ __('overview.report_date_from') }}</label>
                <input type="date" name="from" id="from" value="{{ $from->format('Y-m-d') }}" class="block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>

            {{-- Date to --}}
            <div>
                <label for="to" class="block text-sm font-medium text-gray-700 mb-1">{{ __('overview.report_date_to') }}</label>
                <input type="date" name="to" id="to" value="{{ $to->format('Y-m-d') }}" class="block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            </div>

            {{-- Apply button --}}
            <div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    {{ __('overview.report_filter_apply') }}
                </button>
            </div>
        </form>

        {{-- Quick-filter buttons --}}
        <div class="flex flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100">
            @foreach(['week' => 'report_filter_week', 'month' => 'report_filter_month', '30days' => 'report_filter_30days', 'season' => 'report_filter_season'] as $key => $label)
                <a href="{{ route('admin.overview.customer-report', array_merge(['customer' => $selectedCustomer?->id], $quickFilters[$key])) }}"
                   class="px-3 py-1.5 text-sm rounded-md border {{ $from->format('Y-m-d') === $quickFilters[$key]['from'] && $to->format('Y-m-d') === $quickFilters[$key]['to'] ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }} transition">
                    {{ __('overview.' . $label) }}
                </a>
            @endforeach
        </div>
    </div>

    @if(!$selectedCustomer)
        {{-- No customer selected --}}
        <x-empty-state :heading="__('overview.customer_report_no_customer_selected')" />
    @elseif($totalJobs > 0)
        {{-- Sammel-PDF Button + Email Button --}}
        <div class="mb-6 flex flex-wrap gap-3">
            <a href="{{ $sammelPdfUrl }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                {{ __('overview.customer_report_sammel_pdf') }}
            </a>

            @if($selectedCustomer->notification_email || $selectedCustomer->email)
                <button type="button"
                    x-data
                    x-on:click="$dispatch('open-modal', 'send-report-email')"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:bg-green-500 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                    {{ __('overview.customer_report_send_email') }}
                </button>
            @endif
        </div>

        {{-- Email Confirmation Modal --}}
        @if($selectedCustomer->notification_email || $selectedCustomer->email)
            <x-confirm-dialog name="send-report-email"
                :title="__('overview.customer_report_email_modal_title')"
                :message="__('overview.customer_report_email_modal_message', [
                    'name' => $selectedCustomer->name,
                    'email' => $selectedCustomer->notification_email ?? $selectedCustomer->email,
                    'from' => $from->format('d.m.Y'),
                    'to' => $to->format('d.m.Y'),
                    'count' => $totalJobs,
                ])">
                <x-slot name="action">
                    <form method="POST" action="{{ route('admin.overview.customer-report.email') }}">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $selectedCustomer->id }}">
                        <input type="hidden" name="from" value="{{ $from->format('Y-m-d') }}">
                        <input type="hidden" name="to" value="{{ $to->format('Y-m-d') }}">
                        <x-primary-button class="bg-green-600 hover:bg-green-500">
                            {{ __('overview.customer_report_send_email') }}
                        </x-primary-button>
                    </form>
                </x-slot>
            </x-confirm-dialog>
        @endif

        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            {{-- Total jobs --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.customer_report_total_jobs') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $totalJobs }}</dd>
            </div>

            {{-- Total hours --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.customer_report_total_hours') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">
                    @php $h = intdiv($totalMinutes, 60); $m = $totalMinutes % 60; @endphp
                    {{ $h > 0 ? $h . 'h ' . $m . 'min' : $m . 'min' }}
                </dd>
            </div>

            {{-- Driver count --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.customer_report_driver_count') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ $driverCount }}</dd>
            </div>

            {{-- Job type breakdown --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.customer_report_type_breakdown') }}</dt>
                <dd class="mt-1 space-y-0.5">
                    @foreach($jobTypeBreakdown as $type => $count)
                        <div class="text-sm text-gray-900">{{ __('job.type_' . $type) }}: {{ $count }}</div>
                    @endforeach
                </dd>
            </div>

            {{-- Average duration --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.customer_report_avg_duration') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">
                    @php $ah = intdiv($avgDurationMinutes, 60); $am = $avgDurationMinutes % 60; @endphp
                    {{ $ah > 0 ? $ah . 'h ' . $am . 'min' : $am . 'min' }}
                </dd>
            </div>

            {{-- Frequency --}}
            <div class="bg-white rounded-lg shadow p-4">
                <dt class="text-sm font-medium text-gray-500">{{ __('overview.customer_report_frequency') }}</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900">
                    @if($frequencyPerWeek !== null)
                        {{ $frequencyPerWeek }}
                        <span class="text-sm font-normal text-gray-500">{{ __('overview.customer_report_frequency_per_week') }}</span>
                    @else
                        {{ $totalJobs }}
                        <span class="text-sm font-normal text-gray-500">{{ __('overview.customer_report_frequency_absolute') }}</span>
                    @endif
                </dd>
            </div>
        </div>

        {{-- Job Table --}}
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_object') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_driver') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_start') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_end') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('overview.report_col_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($jobs as $job)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <a href="{{ route('admin.jobs.show', $job) }}" class="text-gray-900 hover:text-indigo-600">{{ $job->localStartedAt()->format('d.m.Y') }}</a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->customerObject?->name ?? '–' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->user->displayName() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->type->label() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->localStartedAt()->format('H:i') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->ended_at ? $job->localEndedAt()->format('H:i') : '—' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    @if($job->ended_at)
                                        @php $jm = $job->started_at->diffInMinutes($job->ended_at); $jh = intdiv($jm, 60); $jr = $jm % 60; @endphp
                                        {{ $jh > 0 ? $jh . 'h ' . $jr . 'min' : $jr . 'min' }}
                                    @else
                                        <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20">
                                            {{ __('overview.active_badge') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        {{-- Customer selected but no jobs --}}
        <x-empty-state :heading="__('overview.customer_report_no_jobs')" />
    @endif
</x-admin-layout>
