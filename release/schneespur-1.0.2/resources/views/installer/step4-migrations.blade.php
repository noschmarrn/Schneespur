@extends('installer.layout')

@section('content')
    <h2 class="text-xl font-semibold mb-4">{{ __('install.title_step_4') }}</h2>

    @if(session('migration_output') && $errors->any())
        @include('installer._migration-error', ['migrationOutput' => session('migration_output')])
    @endif

    @if(session('migration_success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <p class="text-sm font-semibold text-green-700">{{ __('install.migration_success') }}</p>
        </div>
    @endif

    <p class="text-gray-600 mb-6">{{ __('install.migration_heading') }}</p>

    <form method="POST" action="{{ route('install.migrations.run') }}">
        @csrf
        <div class="flex justify-between">
            <a href="{{ route('install.database') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 transition ease-in-out duration-150">
                &larr; {{ __('install.btn_back') }}
            </a>
            <x-primary-button>
                {{ __('install.migration_run_btn') }} &rarr;
            </x-primary-button>
        </div>
    </form>
@endsection
