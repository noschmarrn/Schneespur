<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('errors.404_headline') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md text-center">
            <p class="text-6xl font-bold text-gray-300">404</p>
            <h1 class="mt-4 text-2xl font-semibold text-gray-900">{{ __('errors.404_headline') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('errors.404_text') }}</p>
            <a href="{{ url('/') }}" class="mt-6 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                {{ __('errors.404_back') }}
            </a>
        </div>
    </div>
</body>
</html>
