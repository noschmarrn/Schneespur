@extends('installer.layout')

@section('content')
    <div class="text-center">
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-green-700 mb-2">{{ __('install.title_done') }}</h2>
        <p class="text-gray-600 mb-6">{{ __('install.done_description', ['app_name' => brand()]) }}</p>
    </div>

    @if(session('flash_test_mail'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <p class="text-sm text-green-700">{{ session('flash_test_mail') }}</p>
        </div>
    @endif

    <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-3">
        <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-gray-600">{{ __('install.done_summary_url') }}</span>
            <span class="text-sm text-gray-900">{{ $appUrl }}</span>
        </div>
        <div class="border-t border-gray-200"></div>
        <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-gray-600">{{ __('install.done_summary_admin') }}</span>
            <span class="text-sm text-gray-900">{{ $adminEmail }}</span>
        </div>
        <div class="border-t border-gray-200"></div>
        <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-gray-600">{{ __('install.done_summary_mail') }}</span>
            <span class="text-sm text-gray-900">{{ $mailConfigured ? __('install.done_mail_yes') : __('install.done_mail_no') }}</span>
        </div>
    </div>

    <div class="flex justify-center">
        <a href="{{ url('/login') }}" class="inline-flex items-center px-6 py-3 bg-green-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
            {{ __('install.done_login_btn') }} &rarr;
        </a>
    </div>
@endsection
