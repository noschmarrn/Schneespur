<x-portal-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-800">
            {{ __('portal.welcome', ['name' => auth('customer')->user()->name]) }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            {{ __('portal.season_label', ['season' => $season->label]) }}
        </p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('portal.kpi_total_jobs') }}</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalJobs }}</div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('portal.kpi_total_hours') }}</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">{{ $totalHours }}</div>
        </div>

        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <div class="text-sm font-medium text-gray-500">{{ __('portal.kpi_last_activity') }}</div>
            <div class="mt-1 text-3xl font-semibold text-gray-900">
                @if ($lastJob)
                    {{ $lastJob->started_at->format('d.m.Y H:i') }}
                @else
                    {{ __('portal.kpi_no_activity') }}
                @endif
            </div>
        </div>
    </div>

    {{-- Object Overview --}}
    <div class="mt-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('portal.objects_title') }}</h3>

        @if ($objects->isEmpty())
            <div class="bg-white shadow-sm rounded-lg p-6">
                <p class="text-gray-500">{{ __('portal.no_objects') }}</p>
            </div>
        @else
            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-200">
                @foreach ($objects as $object)
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-900">{{ $object->name }}</div>
                            <div class="text-sm text-gray-500">{{ $object->street }}, {{ $object->city }}</div>
                        </div>
                        <div class="text-sm text-gray-500 text-right whitespace-nowrap">
                            @if ($object->last_job_at)
                                <span class="block text-xs text-gray-400">{{ __('portal.last_job_label') }}</span>
                                {{ \Carbon\Carbon::parse($object->last_job_at)->format('d.m.Y H:i') }}
                            @else
                                &mdash;
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-portal-layout>
