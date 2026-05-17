@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_1', ['app_name' => brand()]) }}</h2>

    <p class="text-gray-600 mb-6">{{ __('install.welcome_intro', ['app_name' => brand()]) }}</p>

    <p class="text-gray-600 mb-6">{{ __('install.welcome_description') }}</p>

    @if(session('status'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <p class="text-sm text-green-700">{{ session('status') }}</p>
        </div>
    @endif

    <div class="mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('install.welcome_steps_heading') }}</h3>
        <ol class="list-decimal list-inside space-y-1 text-sm text-gray-600">
            <li>{{ __('install.stepper_step_1') }}</li>
            <li>{{ __('install.stepper_step_2') }}</li>
            <li>{{ __('install.stepper_step_3') }}</li>
            <li>{{ __('install.stepper_step_4') }}</li>
            <li>{{ __('install.stepper_step_5') }}</li>
            <li>{{ __('install.stepper_step_6') }}</li>
            <li>{{ __('install.stepper_step_7') }}</li>
            <li>{{ __('install.stepper_step_8') }}</li>
            <li>{{ __('install.stepper_step_9') }}</li>
        </ol>
    </div>

    <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-md">
        <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('install.welcome_system_info') }}</h3>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt class="text-gray-500">PHP</dt>
            <dd class="text-gray-800 font-mono">{{ PHP_VERSION }}</dd>
            <dt class="text-gray-500">{{ __('install.welcome_server') }}</dt>
            <dd class="text-gray-800 font-mono">{{ $_SERVER['SERVER_SOFTWARE'] ?? '—' }}</dd>
        </dl>
    </div>

    <form method="POST" action="{{ route('install.welcome.process') }}">
        @csrf
        <div class="flex justify-end">
            <x-primary-button>
                {{ __('install.welcome_start_btn') }} &rarr;
            </x-primary-button>
        </div>
    </form>
@endsection
