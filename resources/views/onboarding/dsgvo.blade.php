<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('dsgvo.page_title') }} — {{ brand() }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-10 bg-gray-100">
            <div class="mb-6">
                <span class="text-2xl font-bold tracking-wide text-gray-800">{{ brand() }}</span>
            </div>

            <div class="w-full max-w-3xl mx-4 sm:mx-auto mb-10 bg-white shadow-md overflow-hidden sm:rounded-lg">
                <div class="p-6 sm:p-8 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-6">
                        <h1 class="text-xl font-semibold text-gray-900">{{ __('dsgvo.notice_heading') }}</h1>
                        <span class="text-sm text-gray-500">
                            {{ __('dsgvo.notice_meta', ['version' => $templateVersion, 'date' => now()->format('d.m.Y')]) }}
                        </span>
                    </div>

                    @if ($companyDataMissing)
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-sm font-semibold text-red-800">{{ __('dsgvo.company_data_missing_title') }}</p>
                            <p class="mt-1 text-sm text-red-700">{{ __('dsgvo.company_data_missing_body') }}</p>
                        </div>
                    @endif

                    <div class="prose prose-sm max-w-none text-gray-700">
                        {!! $dsgvoHtml !!}
                    </div>
                </div>

                <hr class="border-gray-200">

                <form method="POST" action="{{ route('onboarding.dsgvo.confirm') }}" class="p-6 sm:p-8">
                    @csrf

                    <fieldset @if($companyDataMissing) disabled @endif>
                        <legend class="text-lg font-semibold text-gray-900 mb-4">{{ __('dsgvo.confirm_legend') }}</legend>

                        <div class="mb-4">
                            <label class="flex items-start gap-3 cursor-pointer min-h-[44px] py-2">
                                <input type="checkbox"
                                       name="accepted"
                                       id="accepted"
                                       value="1"
                                       class="mt-0.5 h-5 w-5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                       {{ old('accepted') ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700 leading-relaxed">{{ __('dsgvo.confirm_checkbox_label') }}</span>
                            </label>
                            @error('accepted')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-6">
                            <label for="signed_by" class="block text-sm font-medium text-gray-700 mb-1">
                                {{ __('dsgvo.confirm_name_label') }}
                            </label>
                            <input type="text"
                                   name="signed_by"
                                   id="signed_by"
                                   value="{{ old('signed_by') }}"
                                   placeholder="{{ __('dsgvo.confirm_name_placeholder') }}"
                                   class="block w-full h-11 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   required>
                            <p class="mt-1 text-xs text-gray-500">{{ __('dsgvo.confirm_name_helper') }}</p>
                            @error('signed_by')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <button type="submit"
                                    class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                {{ __('dsgvo.confirm_submit') }}
                            </button>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </body>
</html>
