<x-admin-layout>
    <x-slot name="header">{{ __('crontasks.page_title') }}</x-slot>

    <div class="mt-6">
        @if (count($tasks) === 0)
            <x-empty-state :heading="__('crontasks.empty_heading')" :body="__('crontasks.empty_body')" />
        @else
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('crontasks.col_name') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('crontasks.col_schedule') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('crontasks.col_source') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('crontasks.col_last_run') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('crontasks.col_status') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('crontasks.col_duration') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('crontasks.col_enabled') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($tasks as $slug => $entry)
                                @php
                                    $task = $entry['task'];
                                    $lastRun = $entry['last_run'];
                                    $isCore = $task->source() === 'core';
                                @endphp
                                <tr x-data="{ showError: false }">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $task->label() }}
                                        <span class="block text-xs text-gray-400 font-mono">{{ $slug }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        {{ $task->schedule() }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $isCore ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                            {{ $isCore ? __('crontasks.source_core') : __('crontasks.source_module') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if ($lastRun)
                                            {{ \Carbon\Carbon::parse($lastRun->ran_at)->diffForHumans() }}
                                        @else
                                            <span class="text-gray-400">{{ __('crontasks.never_run') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if ($lastRun)
                                            @if ($lastRun->status === 'success')
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-800">
                                                    {{ __('crontasks.status_success') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-800 cursor-pointer" x-on:click="showError = !showError">
                                                    {{ __('crontasks.status_failed') }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if ($lastRun && $lastRun->duration_ms !== null)
                                            {{ number_format($lastRun->duration_ms) }} ms
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                        @if ($isCore)
                                            <span class="text-xs text-gray-400">{{ __('crontasks.always_enabled') }}</span>
                                        @elseif (Gate::check('crontasks.manage'))
                                            <form method="POST" action="{{ route('admin.crontasks.toggle', $slug) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 {{ $task->isEnabled() ? 'bg-indigo-600' : 'bg-gray-200' }}" role="switch" aria-checked="{{ $task->isEnabled() ? 'true' : 'false' }}">
                                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $task->isEnabled() ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                                </button>
                                            </form>
                                        @else
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $task->isEnabled() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ $task->isEnabled() ? __('crontasks.enabled') : __('crontasks.disabled') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                                @if ($lastRun && $lastRun->status === 'failed' && $lastRun->error_message)
                                    <tr x-show="showError" x-cloak>
                                        <td colspan="7" class="px-6 py-3 bg-red-50">
                                            <div class="text-sm text-red-700">
                                                <span class="font-medium">{{ __('crontasks.error_label') }}:</span>
                                                {{ $lastRun->error_message }}
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
