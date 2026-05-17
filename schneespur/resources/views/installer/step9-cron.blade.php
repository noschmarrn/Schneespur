@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_9') }}</h2>

    @if(session('cron_test_success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <p class="text-sm text-green-700">{{ __('install.cron_test_success') }}</p>
        </div>
    @endif

    <p class="text-gray-600 mb-4">{{ __('install.cron_heading', ['app_name' => brand()]) }}</p>

    <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('install.cron_line_label') }}</h3>
        <div class="relative" x-data="{ copied: false }">
            <pre class="bg-gray-800 text-green-300 text-xs p-3 rounded-md overflow-x-auto">{{ $cronLine }}</pre>
            <button type="button"
                x-on:click="navigator.clipboard.writeText('{{ $cronLine }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="absolute top-2 right-2 px-2 py-1 bg-gray-600 text-white text-xs rounded hover:bg-gray-500 transition">
                <span x-show="!copied">{{ __('install.btn_copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('install.btn_copied') }}</span>
            </button>
        </div>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-md p-4 mb-6">
        <h3 class="text-sm font-semibold text-blue-800 mb-2">{{ __('install.cron_instructions_heading') }}</h3>
        <ol class="text-sm text-blue-700 space-y-2 list-decimal list-inside">
            <li>{{ __('install.cron_step_1') }}</li>
            <li>{{ __('install.cron_step_2') }}</li>
            <li>{{ __('install.cron_step_3') }}</li>
        </ol>
    </div>

    @if($cronActive)
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md flex items-center">
            <svg class="w-5 h-5 text-green-500 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <p class="text-sm text-green-700">{{ __('install.cron_active') }}</p>
        </div>
    @endif

    <div class="flex items-center gap-4 mb-4">
        <form method="POST" action="{{ route('install.cron.test') }}">
            @csrf
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition">
                {{ __('install.cron_test_btn') }}
            </button>
        </form>
    </div>

    <p class="text-xs text-gray-500 mb-6">{{ __('install.cron_fallback_note', ['app_name' => brand()]) }}</p>

    <div class="flex justify-between">
        <a href="{{ route('install.mail') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition">
            &larr; {{ __('install.btn_back') }}
        </a>
        <form method="POST" action="{{ route('install.cron.skip') }}">
            @csrf
            <x-primary-button>
                {{ __('install.btn_continue') }} &rarr;
            </x-primary-button>
        </form>
    </div>
@endsection
