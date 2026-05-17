@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_6') }}</h2>

    @if($results === null)
        <p class="text-gray-600 mb-6">{{ __('install.storage_heading') }}</p>

        <form method="POST" action="{{ route('install.storage.run') }}">
            @csrf
            <div class="flex justify-between">
                <a href="{{ route('install.config') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                    &larr; {{ __('install.btn_back') }}
                </a>
                <x-primary-button>
                    {{ __('install.storage_run_btn') }} &rarr;
                </x-primary-button>
            </div>
        </form>
    @else
        @php
            $labels = [
                'storage:link' => __('install.storage_link_label'),
                'config:cache' => __('install.storage_config_cache_label'),
                'view:cache'   => __('install.storage_view_cache_label'),
            ];
            $hasStorageLinkWarning = collect($results)->contains(fn($r) => $r['command'] === 'storage:link' && !$r['success']);
        @endphp

        <div class="space-y-2 mb-6">
            @foreach($results as $result)
                <div @class([
                    'flex items-center justify-between p-3 rounded-md',
                    'bg-green-50' => $result['success'],
                    'bg-yellow-50' => !$result['success'],
                ])>
                    <div class="flex items-center gap-2">
                        @if($result['success'])
                            <span class="text-green-600 font-bold">&#10004;</span>
                        @else
                            <span class="text-yellow-600 font-bold">&#9888;</span>
                        @endif
                        <span class="text-sm font-medium">{{ $labels[$result['command']] ?? $result['command'] }}</span>
                    </div>
                    <span @class([
                        'text-sm',
                        'text-green-700' => $result['success'],
                        'text-yellow-700' => !$result['success'],
                    ])>{{ $result['output'] }}</span>
                </div>
            @endforeach
        </div>

        @if($hasStorageLinkWarning)
            <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                <p class="text-sm text-yellow-700">{{ __('install.storage_link_warning', ['app_name' => brand()]) }}</p>
            </div>
        @endif

        <div class="flex justify-between">
            <a href="{{ route('install.config') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                &larr; {{ __('install.btn_back') }}
            </a>
            <a href="{{ route('install.admin') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('install.btn_continue') }} &rarr;
            </a>
        </div>
    @endif
@endsection
