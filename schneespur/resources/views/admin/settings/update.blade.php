<x-admin-layout>
    <x-slot name="header">{{ __('update.page_title') }} <x-help-icon topic="updates" /></x-slot>

    <div class="max-w-2xl" x-data="{
        checking: false,
        installing: false,
        backupConfirmed: false,
        result: null,
        installResult: null,
        async checkNow() {
            this.checking = true;
            this.result = null;
            this.installResult = null;
            try {
                const resp = await fetch('{{ route('admin.settings.update.check') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                        'Accept': 'application/json',
                    },
                });
                this.result = await resp.json();
            } catch (e) {
                this.result = { ok: false, message: e.message };
            }
            this.checking = false;
        },
        async installUpdate() {
            if (!this.backupConfirmed) return;
            this.installing = true;
            this.installResult = null;
            try {
                const resp = await fetch('{{ route('admin.settings.update.install') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').content,
                        'Accept': 'application/json',
                    },
                });
                this.installResult = await resp.json();
                if (this.installResult.ok) {
                    setTimeout(() => window.location.reload(), 2000);
                }
            } catch (e) {
                this.installResult = { ok: false, message: e.message };
            }
            this.installing = false;
        }
    }">

        @if(session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-4">
                <p class="text-sm text-green-800">{{ session('success') }}</p>
            </div>
        @endif

        @unless($hasSodium)
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-4">
                <p class="text-sm text-red-800">{{ __('update.sodium_missing') }}</p>
            </div>
        @endunless

        {{-- Version + Trust info --}}
        <div class="bg-white shadow-sm rounded-lg p-6 space-y-3">
            <h3 class="text-lg font-medium text-gray-900">{{ __('update.current_version') }}</h3>
            <p class="text-2xl font-semibold text-gray-900">{{ $currentVersion }}</p>

            @if($state)
                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-gray-500">{{ __('update.trust_version') }}</dt>
                    <dd class="text-gray-900">{{ $state['trust_version'] ?: __('update.trust_not_loaded') }}</dd>

                    <dt class="text-gray-500">{{ __('update.trust_expires') }}</dt>
                    <dd class="text-gray-900">
                        @if($state['trust_expires_at'])
                            {{ \Carbon\Carbon::parse($state['trust_expires_at'])->format('d.m.Y') }}
                        @else
                            {{ __('update.trust_not_loaded') }}
                        @endif
                    </dd>

                    <dt class="text-gray-500">{{ __('update.trust_keys') }}</dt>
                    <dd class="text-gray-900">{{ count($state['valid_keys'] ?? []) }}</dd>
                </dl>
            @endif
        </div>

        {{-- Auto-check toggle --}}
        <form method="POST" action="{{ route('admin.settings.update.update') }}" class="mt-6 space-y-6">
            @csrf

            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4">
                <h3 class="text-lg font-medium text-gray-900">{{ __('update.auto_check_label') }}</h3>
                <p class="text-sm text-gray-500">{{ __('update.auto_check_help', ['app_name' => config('app.name')]) }}</p>

                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="auto_update_check" value="1"
                           @checked(old('auto_update_check', $autoCheck))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                    <span class="text-sm text-gray-700">
                        {{ $autoCheck ? __('update.auto_check_enabled') : __('update.auto_check_disabled') }}
                    </span>
                </label>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    {{ __('settings.button_save') }}
                </button>
            </div>
        </form>

        {{-- Check now --}}
        @if($hasSodium)
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4 mt-6">
                <h3 class="text-lg font-medium text-gray-900">{{ __('update.check_now') }}</h3>

                <button type="button" @click="checkNow()" :disabled="checking"
                        class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150">
                    <svg x-show="checking" x-cloak class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span x-show="!checking">{{ __('update.check_now') }}</span>
                    <span x-show="checking" x-cloak>{{ __('update.checking') }}</span>
                </button>

                <div x-show="result" x-cloak>
                    <template x-if="result?.ok && !result?.update">
                        <div class="rounded-md bg-green-50 p-4">
                            <p class="text-sm text-green-800" x-text="result.message"></p>
                        </div>
                    </template>
                    <template x-if="result?.ok && result?.update">
                        <div class="space-y-3">
                            <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4">
                                <p class="text-sm font-medium text-yellow-800" x-text="result.message"></p>
                            </div>
                            <div x-show="result.changelog" class="bg-gray-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-2">{{ __('update.changelog') }}</h4>
                                <div class="prose prose-sm max-w-none" x-html="result.changelog"></div>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm" x-show="result.size_bytes || result.signed_at">
                                <template x-if="result.signed_at">
                                    <div class="contents">
                                        <dt class="text-gray-500">{{ __('update.released_at') }}</dt>
                                        <dd class="text-gray-900" x-text="new Date(result.signed_at).toLocaleDateString()"></dd>
                                    </div>
                                </template>
                                <template x-if="result.size_bytes">
                                    <div class="contents">
                                        <dt class="text-gray-500">{{ __('update.download_size') }}</dt>
                                        <dd class="text-gray-900" x-text="(result.size_bytes / 1024 / 1024).toFixed(1) + ' MB'"></dd>
                                    </div>
                                </template>
                            </dl>
                        </div>
                    </template>
                    <template x-if="result && !result.ok">
                        <div class="rounded-md bg-red-50 p-4">
                            <p class="text-sm text-red-800" x-text="result.message"></p>
                        </div>
                    </template>
                </div>

                {{-- Backup + Install (shown when update found) --}}
                <div x-show="result?.ok && result?.update" x-cloak class="space-y-4 mt-4">
                    <div class="rounded-md bg-amber-50 border border-amber-200 p-4 space-y-2">
                        <h4 class="text-sm font-semibold text-amber-800">{{ __('update.backup_title') }}</h4>
                        <p class="text-sm text-amber-700">{{ __('update.backup_warning') }}</p>
                        <p class="text-sm text-amber-700">
                            {{ __('update.backup_db_info', ['host' => config('database.connections.mysql.host', 'localhost'), 'database' => config('database.connections.mysql.database', '')]) }}
                        </p>
                        <p class="text-sm text-amber-600">{{ __('update.backup_instructions') }}</p>
                        <label class="inline-flex items-center gap-2 mt-2">
                            <input type="checkbox" x-model="backupConfirmed"
                                   class="rounded border-gray-300 text-amber-600 shadow-sm focus:ring-amber-500">
                            <span class="text-sm font-medium text-amber-800">{{ __('update.backup_confirm') }}</span>
                        </label>
                    </div>

                    <button type="button" @click="installUpdate()" :disabled="!backupConfirmed || installing"
                            class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 focus:bg-amber-700 active:bg-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150">
                        <svg x-show="installing" x-cloak class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span x-show="!installing">{{ __('update.install_button') }}</span>
                        <span x-show="installing" x-cloak>{{ __('update.installing') }}</span>
                    </button>

                    <div x-show="installResult" x-cloak>
                        <template x-if="installResult?.ok">
                            <div class="rounded-md bg-green-50 p-4">
                                <p class="text-sm text-green-800" x-text="installResult.message"></p>
                            </div>
                        </template>
                        <template x-if="installResult && !installResult.ok">
                            <div class="rounded-md bg-red-50 p-4">
                                <p class="text-sm text-red-800" x-text="installResult.message"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif

        {{-- Preflight --}}
        @if($preflight)
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-3 mt-6">
                <h3 class="text-sm font-medium text-gray-900">{{ __('update.preflight_title') }}</h3>
                @php $allOk = !in_array(false, $preflight, true); @endphp
                @if($allOk)
                    <p class="text-sm text-green-700">{{ __('update.preflight_ok') }}</p>
                @else
                    <p class="text-sm text-red-700">{{ __('update.preflight_fail') }}</p>
                @endif
                <ul class="text-sm space-y-1">
                    @foreach($preflight as $check => $ok)
                        <li class="flex items-center gap-2">
                            @if($ok)
                                <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            @else
                                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            @endif
                            <span class="{{ $ok ? 'text-gray-700' : 'text-red-700' }}">{{ __('update.preflight_' . $check) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Last check info --}}
        @if($state && $state['last_check'])
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-2 mt-6">
                <h3 class="text-sm font-medium text-gray-500">{{ __('update.dashboard_last_checked') }}</h3>
                <p class="text-sm text-gray-900">{{ \Carbon\Carbon::parse($state['last_check']['checked_at'])->diffForHumans() }}</p>
                @if($state['last_check']['has_update'])
                    <p class="text-sm font-medium text-yellow-700">
                        {{ __('update.check_result_update', ['version' => $state['last_check']['latest_version']]) }}
                    </p>
                @else
                    <p class="text-sm text-green-700">
                        {{ __('update.check_result_up_to_date', ['app_name' => config('app.name')]) }}
                    </p>
                @endif
            </div>
        @endif

    </div>
</x-admin-layout>
