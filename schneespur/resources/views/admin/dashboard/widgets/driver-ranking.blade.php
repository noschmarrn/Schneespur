@php $driverRanking = $widget['data']['driverRanking']; @endphp
<div class="mt-8">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('dashboard.driver_ranking') }}</h3>

    @if ($driverRanking->every(fn ($d) => $d->season_jobs === 0))
        <div class="bg-white overflow-hidden shadow-sm rounded-lg p-6">
            <p class="text-sm text-gray-500">{{ __('dashboard.no_jobs_in_season') }}</p>
        </div>
    @else
        <div class="bg-white overflow-hidden shadow-sm rounded-lg" x-data="{
            sortBy: 'jobs',
            sortDir: 'desc',
            drivers: {{ Js::from($driverRanking->map(fn ($d) => ['name' => $d->driver->displayName(), 'jobs' => $d->season_jobs, 'minutes' => $d->total_minutes])) }},
            get sorted() {
                const key = this.sortBy === 'jobs' ? 'jobs' : 'minutes';
                const dir = this.sortDir === 'asc' ? 1 : -1;
                return [...this.drivers].sort((a, b) => (a[key] - b[key]) * dir);
            },
            toggleSort(col) {
                if (this.sortBy === col) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = col;
                    this.sortDir = 'desc';
                }
            },
            formatHours(minutes) {
                return Math.floor(minutes / 60) + 'h ' + (minutes % 60) + 'm';
            }
        }">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('dashboard.col_driver') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none" @click="toggleSort('jobs')">
                                {{ __('dashboard.col_jobs') }}
                                <span x-show="sortBy === 'jobs'" x-text="sortDir === 'asc' ? '↑' : '↓'" class="ml-1"></span>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer select-none" @click="toggleSort('hours')">
                                {{ __('dashboard.col_hours') }}
                                <span x-show="sortBy === 'hours'" x-text="sortDir === 'asc' ? '↑' : '↓'" class="ml-1"></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="(d, index) in sorted" :key="index">
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-900" x-text="d.name"></td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right" x-text="d.jobs"></td>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right" x-text="formatHours(d.minutes)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
