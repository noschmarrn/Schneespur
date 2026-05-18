<x-admin-layout>
    <x-slot name="header">{{ __('job.page_list') }} <x-help-icon topic="jobs" /></x-slot>

    <div class="flex items-center justify-end">
        <a href="{{ route('admin.jobs.manual.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('job.manual_create_title') }}
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.jobs.index') }}" class="mt-4 space-y-2"
          x-data="{
              customerId: '{{ request('customer_id', '') }}',
              objectId: '{{ request('customer_object_id', '') }}',
              objects: @js($objects->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])),
              async fetchObjects() {
                  if (!this.customerId) { this.objects = []; this.objectId = ''; return; }
                  const res = await fetch(`/admin/customers/${this.customerId}/objects/json`);
                  this.objects = await res.json();
                  if (!this.objects.find(o => o.id == this.objectId)) this.objectId = '';
              }
          }">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-7">
            <div>
                <label for="started_after" class="block text-sm font-medium text-gray-700">{{ __('job.filter_date_from') }}</label>
                <input type="date" name="started_after" id="started_after" value="{{ request('started_after') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="started_before" class="block text-sm font-medium text-gray-700">{{ __('job.filter_date_to') }}</label>
                <input type="date" name="started_before" id="started_before" value="{{ request('started_before') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            </div>
            <div>
                <label for="user_id" class="block text-sm font-medium text-gray-700">{{ __('job.filter_driver') }}</label>
                <select name="user_id" id="user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('job.filter_all') }}</option>
                    @foreach ($drivers as $driver)
                        <option value="{{ $driver->id }}" @selected(request('user_id') == $driver->id)>{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="customer_id" class="block text-sm font-medium text-gray-700">{{ __('job.filter_customer') }}</label>
                <select name="customer_id" id="customer_id" x-model="customerId" @change="fetchObjects()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('job.filter_all') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(request('customer_id') == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="customer_object_id" class="block text-sm font-medium text-gray-700">{{ __('job.filter_object') }}</label>
                <select name="customer_object_id" id="customer_object_id" x-model="objectId" :disabled="!customerId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400">
                    <option value="">{{ __('job.filter_all') }}</option>
                    <template x-for="obj in objects" :key="obj.id">
                        <option :value="obj.id" x-text="obj.name" :selected="obj.id == objectId"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">{{ __('job.filter_type') }}</label>
                <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">{{ __('job.filter_all') }}</option>
                    @foreach ($jobTypes as $jobType)
                        <option value="{{ $jobType->value }}" @selected(request('type') === $jobType->value)>{{ $jobType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('job.filter_btn') }}
                </button>
                <a href="{{ route('admin.jobs.index') }}" class="text-sm text-gray-600 hover:text-gray-900 underline">{{ __('job.filter_reset') }}</a>
            </div>
        </div>
        <x-date-range-presets fromId="started_after" toId="started_before" />
    </form>

    <div class="mt-6">
        @if ($jobs->count())
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_date') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_driver') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_customer') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_type') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_duration') }}</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_gps_points') }}</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('job.col_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($jobs as $job)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $job->localStartedAt()->format('d.m.Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->user->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $job->customerObject?->customer?->name ?? $job->customer?->name ?? '–' }}
                                        @if($job->customerObject)
                                            <span class="text-gray-400">/ {{ $job->customerObject->name }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $job->type->label() }}
                                        @if ($job->is_manual)
                                            <span class="ml-1 inline-flex items-center rounded-full bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700 ring-1 ring-inset ring-yellow-600/20">{{ __('job.detail_manual_badge') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if ($job->ended_at)
                                            {{ $job->durationFormatted() }}
                                            @if ($job->isLocked())
                                                <svg class="inline w-4 h-4 ml-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="{{ __('job.lock_closed') }}"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            @elseif ($job->isInGracePeriod())
                                                <svg class="inline w-4 h-4 ml-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="{{ __('job.lock_open') }}"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">{{ __('job.status_active') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $job->gps_points_count }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('admin.jobs.show', $job) }}" class="text-indigo-600 hover:text-indigo-900">{{ __('job.btn_view') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $jobs->links() }}
            </div>
        @else
            <x-empty-state :heading="__('job.empty_heading')" :body="__('job.empty_body')">
                <x-slot name="action">
                    <a href="{{ route('admin.jobs.manual.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('job.manual_create_title') }}
                    </a>
                </x-slot>
            </x-empty-state>
        @endif
    </div>
</x-admin-layout>
