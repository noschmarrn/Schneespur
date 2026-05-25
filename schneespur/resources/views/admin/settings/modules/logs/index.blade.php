<x-admin-layout>
    <x-slot name="header">{{ __('modules.logs_title') }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => __('modules.page_title'), 'url' => route('admin.settings.modules.index')],
        ['label' => $module->name ?? $module->slug],
        ['label' => __('modules.logs_title')],
    ]" />

    <div class="mt-6 max-w-4xl">
        {{-- Level Filter --}}
        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-gray-600">{{ __('modules.logs_description', ['name' => $module->name ?? $module->slug]) }}</p>
            <form method="GET" action="{{ route('admin.settings.modules.logs', $module->slug) }}">
                <label for="level" class="sr-only">{{ __('modules.log_filter_label') }}</label>
                <select id="level" name="level" onchange="this.form.submit()"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">{{ __('modules.log_filter_all') }}</option>
                    <option value="info" @selected($currentLevel === 'info')>{{ __('modules.log_level_info') }}</option>
                    <option value="warning" @selected($currentLevel === 'warning')>{{ __('modules.log_level_warning') }}</option>
                    <option value="error" @selected($currentLevel === 'error')>{{ __('modules.log_level_error') }}</option>
                </select>
            </form>
        </div>

        {{-- Log Table --}}
        @if($logs->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <p class="mt-4 text-sm text-gray-500">{{ __('modules.no_logs') }}</p>
            </div>
        @else
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.log_col_time') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.log_col_level') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.log_col_message') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('modules.log_col_context') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    @if($log->level === 'error')
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('modules.log_level_error') }}</span>
                                    @elseif($log->level === 'warning')
                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700">{{ __('modules.log_level_warning') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">{{ __('modules.log_level_info') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $log->message }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500 font-mono">
                                    @if($log->context)
                                        <span title="{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}">{{ Str::limit(json_encode($log->context, JSON_UNESCAPED_UNICODE), 80) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $logs->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>
