<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ brand() }} — Installation</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Inline fallback if Vite assets fail -->
        <style>
            .installer-fallback { font-family: ui-sans-serif, system-ui, sans-serif; max-width: 42rem; margin: 2rem auto; padding: 1.5rem; }
            .installer-fallback h1 { font-size: 1.5rem; margin-bottom: 1rem; }
            .installer-fallback input, .installer-fallback select { display: block; width: 100%; padding: .5rem; margin: .25rem 0 .75rem; border: 1px solid #d1d5db; border-radius: .375rem; }
            .installer-fallback button { padding: .5rem 1.5rem; background: #2563eb; color: #fff; border: none; border-radius: .375rem; cursor: pointer; }
            .installer-fallback .error { color: #dc2626; font-size: .875rem; }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col items-center pt-6 sm:pt-12 bg-gray-100 installer-fallback">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">❄ {{ brand() }}</h1>
            </div>

            @include('installer._stepper', ['currentStep' => $currentStep ?? 1])

            <div class="w-full sm:max-w-2xl mt-6 px-6 py-6 bg-white shadow-md overflow-hidden sm:rounded-lg">
                @yield('content')
            </div>
        </div>
    </body>
</html>
