@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_2') }}</h2>

    <p class="text-gray-600 mb-4">{{ __('install.preflight_heading', ['app_name' => brand()]) }}</p>

    @php
        $hasWarnings = collect($checks)->contains('status', 'warn');
    @endphp

    @if($hasCritical)
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm font-semibold text-red-700">{{ __('install.preflight_has_failures') }}</p>
        </div>
    @elseif($hasWarnings)
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <p class="text-sm font-semibold text-yellow-700">{{ __('install.preflight_has_warnings') }}</p>
        </div>
    @else
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <p class="text-sm font-semibold text-green-700">{{ __('install.preflight_all_passed') }}</p>
        </div>
    @endif

    <div class="space-y-2 mb-6">
        @foreach($checks as $check)
            <div @class([
                'flex items-center justify-between p-3 rounded-md',
                'bg-green-50' => $check['status'] === 'pass',
                'bg-yellow-50' => $check['status'] === 'warn',
                'bg-red-50' => $check['status'] === 'fail',
            ])>
                <div class="flex items-center gap-2">
                    @if($check['status'] === 'pass')
                        <span class="text-green-600 font-bold">&#10004;</span>
                    @elseif($check['status'] === 'warn')
                        <span class="text-yellow-600 font-bold">&#9888;</span>
                    @else
                        <span class="text-red-600 font-bold">&#10008;</span>
                    @endif
                    <span class="text-sm font-medium text-gray-800">{{ $check['name'] }}</span>
                </div>
                <span @class([
                    'text-sm',
                    'text-green-700' => $check['status'] === 'pass',
                    'text-yellow-700' => $check['status'] === 'warn',
                    'text-red-700' => $check['status'] === 'fail',
                ])>{{ $check['message'] }}</span>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('install.preflight.process') }}">
        @csrf
        <div class="flex justify-between">
            <a href="{{ route('install.welcome') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                &larr; {{ __('install.btn_back') }}
            </a>
            <x-primary-button :disabled="$hasCritical" class="{{ $hasCritical ? 'opacity-50 cursor-not-allowed' : '' }}">
                {{ __('install.preflight_continue_btn') }} &rarr;
            </x-primary-button>
        </div>
    </form>
@endsection
