<x-driver-layout :shiftStatus="isset($shiftActive) && $shiftActive ? 'active' : 'inactive'">
    <div
        x-data="driverDashboard()"
        x-init="init()"
        @submit="handleFormSubmit($event)"
        class="space-y-4"
    >
        {{-- Offline queue flash message (client-side) --}}
        <div x-show="offlineFlash"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="mb-4 rounded-lg p-4 bg-amber-900/50 border border-amber-700"
             role="status">
            <div class="flex items-center">
                <div class="shrink-0">
                    <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <p class="ml-3 text-sm font-medium text-amber-300" x-text="offlineFlash"></p>
            </div>
        </div>

        {{-- ========== IDLE STATE: No active shift ========== --}}
        <template x-if="state === 'idle'">
            <div class="flex flex-col items-center justify-center min-h-[60vh] px-4">
                <div class="text-center mb-8">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-800 mb-4">
                        <svg class="w-10 h-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-300">{{ __('driver.dash_no_shift') }}</h2>
                    <p class="mt-1 text-sm text-gray-500">{{ __('driver.dash_no_shift_hint') }}</p>
                </div>

                <form method="POST" action="{{ route('driver.shift.start') }}">
                    @csrf
                    @lifecycleFields('shift.start')
                    <button
                        type="submit"
                        class="min-h-[56px] min-w-[220px] px-8 py-4 bg-green-600 hover:bg-green-500 active:bg-green-700 text-white text-lg font-semibold rounded-xl shadow-lg transition focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 focus:ring-offset-gray-900"
                    >
                        {{ __('driver.dash_shift_start') }}
                    </button>
                </form>

                <a href="{{ route('driver.jobs.index') }}"
                   class="mt-4 inline-flex items-center gap-2 text-sm text-gray-400 hover:text-gray-200 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    {{ __('driver.nav_history') }}
                </a>
            </div>
        </template>

        {{-- ========== SHIFT ACTIVE STATE: Shift running, no job ========== --}}
        <template x-if="state === 'shift_active'">
            <div class="space-y-6">
                {{-- Shift info bar --}}
                <div class="bg-gray-800 rounded-xl p-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-400">{{ __('driver.dash_shift_since') }}</p>
                        <p class="text-lg font-semibold text-green-400" x-text="shiftStartedFormatted"></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-400">{{ __('driver.dash_shift_duration') }}</p>
                        <p class="text-lg font-mono font-semibold text-gray-200" x-text="shiftDuration"></p>
                    </div>
                </div>

                {{-- Customer selector --}}
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">{{ __('driver.dash_select_customer') }}</label>
                    <div class="bg-gray-800 rounded-xl overflow-hidden max-h-[240px] overflow-y-auto border border-gray-700">
                        @forelse($customers ?? [] as $customer)
                            <button
                                type="button"
                                @click="selectCustomer({{ $customer->id }}, '{{ addslashes($customer->name) }}')"
                                :class="selectedCustomerId === {{ $customer->id }}
                                    ? 'bg-blue-600 text-white border-blue-500'
                                    : 'text-gray-300 hover:bg-gray-700 border-transparent'"
                                class="w-full text-left min-h-[56px] px-4 py-3 border-b border-gray-700 last:border-b-0 transition text-base font-medium flex items-center"
                            >
                                <span class="truncate">{{ $customer->name }}</span>
                                <span class="ml-auto text-sm opacity-60 truncate max-w-[120px]">{{ $customer->objects->first()?->street }}</span>
                            </button>
                        @empty
                            <div class="px-4 py-6 text-center text-gray-500 text-sm">
                                {{ __('driver.dash_no_customers') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Object selector (shown when customer has >1 objects) --}}
                <div x-show="selectedCustomerId && customerObjects.length > 1" x-transition>
                    <label class="block text-sm font-medium text-gray-300 mb-2">{{ __('driver.dash_select_object') }}</label>
                    <div class="bg-gray-800 rounded-xl overflow-hidden max-h-[200px] overflow-y-auto border border-gray-700">
                        <template x-for="obj in customerObjects" :key="obj.id">
                            <button
                                type="button"
                                @click="selectedObjectId = obj.id"
                                :class="selectedObjectId === obj.id
                                    ? 'bg-blue-600 text-white border-blue-500'
                                    : 'text-gray-300 hover:bg-gray-700 border-transparent'"
                                class="w-full text-left min-h-[48px] px-4 py-3 border-b border-gray-700 last:border-b-0 transition text-base font-medium flex items-center"
                            >
                                <span class="truncate" x-text="obj.name"></span>
                                <span class="ml-auto text-sm opacity-60 truncate max-w-[160px]" x-text="[obj.street, obj.city].filter(Boolean).join(', ')"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Job type picker --}}
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">{{ __('driver.dash_select_type') }}</label>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach(app(\App\Services\Extension\JobTypeRegistry::class)->types() as $type)
                            <button
                                type="button"
                                @click="selectedJobType = '{{ $type->value }}'"
                                :class="selectedJobType === '{{ $type->value }}'
                                    ? 'bg-blue-600 text-white border-blue-500 ring-2 ring-blue-400'
                                    : 'bg-gray-800 text-gray-300 border-gray-700 hover:bg-gray-700'"
                                class="min-h-[56px] px-4 py-3 rounded-xl border-2 text-base font-semibold transition text-center"
                            >
                                {{ $type->label() }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Vehicle selector --}}
                @if(($vehicles ?? collect())->isNotEmpty())
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">{{ __('driver.dash_select_vehicle') }}</label>
                    <div class="bg-gray-800 rounded-xl overflow-hidden max-h-[200px] overflow-y-auto border border-gray-700">
                        <button
                            type="button"
                            @click="selectedVehicleId = null"
                            :class="selectedVehicleId === null
                                ? 'bg-blue-600 text-white border-blue-500'
                                : 'text-gray-400 hover:bg-gray-700 border-transparent'"
                            class="w-full text-left min-h-[48px] px-4 py-2 border-b border-gray-700 transition text-sm font-medium italic"
                        >
                            {{ __('driver.dash_vehicle_none') }}
                        </button>
                        @foreach($vehicles as $vehicle)
                            <button
                                type="button"
                                @click="selectedVehicleId = {{ $vehicle->id }}"
                                :class="selectedVehicleId === {{ $vehicle->id }}
                                    ? 'bg-blue-600 text-white border-blue-500'
                                    : 'text-gray-300 hover:bg-gray-700 border-transparent'"
                                class="w-full text-left min-h-[48px] px-4 py-2 border-b border-gray-700 last:border-b-0 transition text-base font-medium flex items-center"
                            >
                                <span class="truncate">{{ $vehicle->name }}</span>
                                @if($vehicle->license_plate)
                                    <span class="ml-auto text-sm opacity-60">{{ $vehicle->license_plate }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Start job button --}}
                <form method="POST" action="{{ route('driver.job.start') }}" x-ref="jobStartForm">
                    @csrf
                    <input type="hidden" name="customer_object_id" :value="selectedObjectId">
                    <input type="hidden" name="type" :value="selectedJobType">
                    <input type="hidden" name="vehicle_id" :value="selectedVehicleId">
                    @lifecycleFields('job.start')
                    <button
                        type="submit"
                        :disabled="!selectedObjectId || !selectedJobType"
                        :class="selectedObjectId && selectedJobType
                            ? 'bg-green-600 hover:bg-green-500 active:bg-green-700'
                            : 'bg-gray-700 text-gray-500 cursor-not-allowed'"
                        class="w-full min-h-[56px] px-6 py-4 text-white text-lg font-semibold rounded-xl shadow-lg transition focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2 focus:ring-offset-gray-900"
                    >
                        {{ __('driver.dash_job_start') }}
                    </button>
                </form>

                {{-- End shift (secondary) --}}
                <form method="POST" action="{{ route('driver.shift.end') }}">
                    @csrf
                    @lifecycleFields('shift.end')
                    <button
                        type="submit"
                        class="w-full min-h-[56px] px-6 py-3 bg-gray-800 hover:bg-gray-700 active:bg-gray-600 text-gray-300 text-base font-medium rounded-xl border border-gray-600 transition focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2 focus:ring-offset-gray-900"
                    >
                        {{ __('driver.dash_shift_end') }}
                    </button>
                </form>
            </div>
        </template>

        {{-- ========== JOB ACTIVE STATE: Job in progress ========== --}}
        <template x-if="state === 'job_active'">
            <div class="space-y-6">
                {{-- Active job info --}}
                <div class="bg-gray-800 rounded-xl p-5 space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-3 w-3 rounded-full bg-green-400 animate-pulse"></span>
                        <span class="text-sm font-medium text-green-400">{{ __('driver.dash_job_active') }}</span>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-400">{{ __('driver.dash_customer') }}</p>
                            <p class="text-lg font-semibold text-gray-100" x-text="activeJob.customer_name"></p>
                        </div>
                        <div x-show="activeJob.object_name">
                            <p class="text-sm text-gray-400">{{ __('driver.dash_object') }}</p>
                            <p class="text-base font-medium text-gray-200" x-text="activeJob.object_name"></p>
                        </div>
                        <div class="flex gap-6">
                            <div>
                                <p class="text-sm text-gray-400">{{ __('driver.dash_job_type') }}</p>
                                <p class="text-base font-medium text-gray-200" x-text="activeJob.type_label"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">{{ __('driver.dash_job_since') }}</p>
                                <p class="text-base font-mono font-medium text-gray-200" x-text="jobStartedFormatted"></p>
                            </div>
                        </div>
                        <div x-show="activeJob.vehicle_label">
                            <p class="text-sm text-gray-400">{{ __('job.field_vehicle') }}</p>
                            <p class="text-base font-medium text-gray-200" x-text="activeJob.vehicle_label"></p>
                        </div>
                        <div class="flex gap-6">
                            <div>
                                <p class="text-sm text-gray-400">{{ __('driver.dash_job_duration') }}</p>
                                <p class="text-2xl font-mono font-bold text-white" x-text="jobDuration"></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-400">{{ __('driver.dash_gps_points') }}</p>
                                <p class="text-2xl font-mono font-bold text-blue-400" x-text="activeJob.gps_points_count ?? 0"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Photo capture + gallery --}}
                <div class="bg-gray-800 rounded-xl p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-medium text-gray-300">{{ __('driver.dash_photos') }}</h3>
                        <span class="text-xs text-gray-500" x-text="photos.length + '/' + maxPhotos"></span>
                    </div>

                    {{-- Camera button --}}
                    <div>
                        {{-- Offline warning --}}
                        <p x-show="!isOnline && photosRemaining > 0"
                           class="mb-2 text-xs text-amber-400">{{ __('driver.dash_photo_offline_hint') }}</p>

                        <label
                            class="flex items-center justify-center gap-2 w-full min-h-[56px] px-6 py-4 text-white text-lg font-semibold rounded-xl shadow-lg transition cursor-pointer"
                            :class="photoUploading || photosRemaining <= 0 || !isOnline
                                ? 'bg-gray-700 text-gray-500 cursor-not-allowed pointer-events-none'
                                : 'bg-blue-600 hover:bg-blue-500 active:bg-blue-700'"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span x-text="photoButtonLabel"></span>
                            <input
                                type="file"
                                accept="image/*"
                                capture="environment"
                                class="hidden"
                                @change="uploadPhoto($event)"
                                :disabled="photoUploading || photosRemaining <= 0 || !isOnline"
                            >
                        </label>
                    </div>

                    {{-- Photo gallery --}}
                    <div class="grid grid-cols-3 gap-2" x-show="photos.length > 0">
                        <template x-for="photo in photos" :key="photo.id">
                            <a :href="photo.full_url" target="_blank" class="block aspect-square rounded-lg overflow-hidden bg-gray-700">
                                <img :src="photo.thumbnail_url" :alt="photo.caption || '{{ __('driver.dash_photo_alt') }}'" class="w-full h-full object-cover">
                            </a>
                        </template>
                    </div>
                    <p x-show="photos.length === 0" class="text-sm text-gray-500">{{ __('driver.dash_photos_empty') }}</p>
                </div>

                {{-- End job form --}}
                <form method="POST" action="{{ route('driver.job.end') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="job-notes" class="block text-sm font-medium text-gray-300 mb-2">{{ __('driver.dash_notes_label') }}</label>
                        <textarea
                            id="job-notes"
                            name="notes"
                            rows="3"
                            maxlength="1000"
                            placeholder="{{ __('driver.dash_notes_placeholder') }}"
                            class="w-full min-h-[56px] rounded-xl bg-gray-800 border border-gray-600 text-gray-100 placeholder-gray-500 px-4 py-3 text-base focus:ring-2 focus:ring-red-400 focus:border-transparent transition resize-none"
                        ></textarea>
                    </div>

                    @lifecycleFields('job.end')

                    <button
                        type="submit"
                        class="w-full min-h-[56px] px-6 py-4 bg-red-600 hover:bg-red-500 active:bg-red-700 text-white text-lg font-semibold rounded-xl shadow-lg transition focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-gray-900"
                    >
                        {{ __('driver.dash_job_end') }}
                    </button>
                </form>
            </div>
        </template>

        {{-- Loading state --}}
        <template x-if="state === 'loading'">
            <div class="flex items-center justify-center min-h-[60vh]">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-green-400"></div>
            </div>
        </template>
    </div>

    @php
        $allCustomerObjects = $customers->flatMap(fn ($c) => $c->objects->map(fn ($o) => [
            'id' => $o->id, 'customer_id' => $c->id, 'name' => $o->name, 'street' => $o->street, 'city' => $o->city,
        ]));
    @endphp
    <script>
        function driverDashboard() {
            return {
                state: 'loading',
                shiftData: null,
                activeJob: {},
                selectedCustomerId: null,
                selectedCustomerName: '',
                selectedObjectId: null,
                customerObjects: [],
                allCustomerObjects: @json($allCustomerObjects),
                selectedJobType: null,
                selectedVehicleId: {{ $defaultVehicleId ?? 'null' }},
                photos: [],
                photoUploading: false,
                maxPhotos: {{ \App\Services\PhotoService::MAX_PHOTOS_PER_JOB }},
                photosRemaining: {{ \App\Services\PhotoService::MAX_PHOTOS_PER_JOB }},
                isOnline: navigator.onLine,
                pollInterval: null,
                timerInterval: null,
                shiftDuration: '00:00:00',
                jobDuration: '00:00:00',
                shiftStartedFormatted: '',
                jobStartedFormatted: '',
                offlineFlash: '',
                offlineFlashTimer: null,
                offlineQueueLabel: @json(__('driver.offline_queued')),

                get photoButtonLabel() {
                    if (this.photoUploading) return @json(__('driver.dash_photo_uploading'));
                    if (!this.isOnline) return @json(__('driver.dash_photo_offline_hint'));
                    if (this.photosRemaining <= 0) return @json(__('driver.dash_photo_limit_reached'));
                    return @json(__('driver.dash_photo_capture'));
                },

                selectCustomer(id, name) {
                    this.selectedCustomerId = id;
                    this.selectedCustomerName = name;
                    this.customerObjects = this.allCustomerObjects.filter(o => o.customer_id === id);
                    if (this.customerObjects.length === 1) {
                        this.selectedObjectId = this.customerObjects[0].id;
                    } else {
                        this.selectedObjectId = null;
                    }
                },

                async init() {
                    await this.fetchState();
                    this.startTimer();
                    window.addEventListener('online', () => { this.isOnline = true; });
                    window.addEventListener('offline', () => { this.isOnline = false; });
                },

                async handleFormSubmit(event) {
                    if (navigator.onLine) return;

                    const form = event.target;
                    if (form.tagName !== 'FORM' || form.method.toUpperCase() !== 'POST') return;

                    event.preventDefault();
                    event.stopPropagation();

                    const formData = new FormData(form);
                    const data = {};
                    for (const [key, value] of formData.entries()) {
                        data[key] = value;
                    }

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || data._token || '';

                    const queue = window.foregroundSync?.queue;
                    if (!queue) return;

                    await queue.addRequest({
                        url: form.action,
                        method: 'POST',
                        data: data,
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                    });

                    const count = await queue.getCount();
                    window.dispatchEvent(new CustomEvent('sync:start', { detail: { count } }));

                    this.offlineFlash = this.offlineQueueLabel;
                    if (this.offlineFlashTimer) clearTimeout(this.offlineFlashTimer);
                    this.offlineFlashTimer = setTimeout(() => { this.offlineFlash = ''; }, 5000);
                },

                async fetchState() {
                    try {
                        const resp = await fetch('{{ route("driver.job.active") }}', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await resp.json();

                        this.shiftData = data.shift;

                        if (data.job) {
                            this.activeJob = data.job;
                            this.photos = data.job.photos || [];
                            this.photosRemaining = data.job.photos_remaining ?? (this.maxPhotos - this.photos.length);
                            this.state = 'job_active';
                            this.jobStartedFormatted = this.formatTime(data.job.started_at);
                            this.startPolling();
                        } else if (data.shift) {
                            this.state = 'shift_active';
                            this.shiftStartedFormatted = this.formatTime(data.shift.started_at);
                            this.stopPolling();
                        } else {
                            this.state = 'idle';
                            this.stopPolling();
                        }

                        this.updateHeaderBadge(data.shift !== null);
                    } catch (e) {
                        if (this.state === 'loading') {
                            this.state = 'idle';
                        }
                    }
                },

                startPolling() {
                    this.stopPolling();
                    this.pollInterval = setInterval(() => this.fetchState(), 30000);
                },

                stopPolling() {
                    if (this.pollInterval) {
                        clearInterval(this.pollInterval);
                        this.pollInterval = null;
                    }
                },

                startTimer() {
                    this.updateTimers();
                    this.timerInterval = setInterval(() => this.updateTimers(), 1000);
                },

                updateTimers() {
                    if (this.shiftData?.started_at) {
                        this.shiftDuration = this.elapsed(this.shiftData.started_at);
                    }
                    if (this.activeJob?.started_at) {
                        this.jobDuration = this.elapsed(this.activeJob.started_at);
                    }
                },

                elapsed(isoStr) {
                    const diff = Math.floor((Date.now() - new Date(isoStr).getTime()) / 1000);
                    const h = Math.floor(diff / 3600);
                    const m = Math.floor((diff % 3600) / 60);
                    const s = diff % 60;
                    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                },

                formatTime(isoStr) {
                    const d = new Date(isoStr);
                    return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
                },

                updateHeaderBadge(shiftActive) {
                    const badge = document.querySelector('[data-shift-badge]');
                    if (!badge) return;
                    if (shiftActive) {
                        badge.classList.remove('bg-gray-700', 'text-gray-400');
                        badge.classList.add('bg-green-900', 'text-green-300');
                        const dot = badge.querySelector('[data-shift-dot]');
                        if (dot) {
                            dot.classList.remove('bg-gray-500');
                            dot.classList.add('bg-green-400');
                        }
                        const text = badge.querySelector('[data-shift-text]');
                        if (text) text.textContent = @json(__('driver.shift_active'));
                    } else {
                        badge.classList.remove('bg-green-900', 'text-green-300');
                        badge.classList.add('bg-gray-700', 'text-gray-400');
                        const dot = badge.querySelector('[data-shift-dot]');
                        if (dot) {
                            dot.classList.remove('bg-green-400');
                            dot.classList.add('bg-gray-500');
                        }
                        const text = badge.querySelector('[data-shift-text]');
                        if (text) text.textContent = @json(__('driver.shift_inactive'));
                    }
                },

                async resizeImage(file, maxPx = 1920) {
                    return new Promise((resolve) => {
                        const img = new Image();
                        img.onload = () => {
                            let { width, height } = img;
                            if (width <= maxPx && height <= maxPx) {
                                resolve(file);
                                return;
                            }
                            const ratio = Math.min(maxPx / width, maxPx / height);
                            width = Math.round(width * ratio);
                            height = Math.round(height * ratio);
                            const canvas = document.createElement('canvas');
                            canvas.width = width;
                            canvas.height = height;
                            canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                            canvas.toBlob((blob) => {
                                resolve(new File([blob], file.name, { type: 'image/jpeg' }));
                            }, 'image/jpeg', 0.80);
                        };
                        img.src = URL.createObjectURL(file);
                    });
                },

                async uploadPhoto(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    if (this.photosRemaining <= 0 || !this.isOnline) return;

                    this.photoUploading = true;

                    try {
                        const resized = await this.resizeImage(file);
                        const formData = new FormData();
                        formData.append('photo', resized);

                        const resp = await fetch('{{ route("driver.job.photo.store") }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json',
                            },
                            body: formData,
                        });

                        if (resp.ok) {
                            const data = await resp.json();
                            this.photos.push({
                                id: data.id,
                                thumbnail_url: data.thumbnail_url,
                                full_url: data.thumbnail_url.replace('/thumbs/', '/'),
                                caption: null,
                            });
                            this.photosRemaining = data.photos_remaining ?? (this.maxPhotos - this.photos.length);
                        } else if (resp.status === 422) {
                            const errData = await resp.json();
                            if (errData.message) {
                                this.offlineFlash = errData.message;
                                if (this.offlineFlashTimer) clearTimeout(this.offlineFlashTimer);
                                this.offlineFlashTimer = setTimeout(() => { this.offlineFlash = ''; }, 5000);
                            }
                        }
                    } catch (e) {
                        // silently fail — photo will appear on next poll
                    } finally {
                        this.photoUploading = false;
                        event.target.value = '';
                    }
                },

                destroy() {
                    this.stopPolling();
                    if (this.timerInterval) clearInterval(this.timerInterval);
                }
            };
        }
    </script>
</x-driver-layout>
